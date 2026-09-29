import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RecoveryCodesPanel from '~/features/identity/components/RecoveryCodesPanel.vue'
import TwoFactorSettings from '~/features/identity/components/TwoFactorSettings.vue'
import { alex, apiError, createFakeApi } from '../support/fakeApi'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)

const fake = createFakeApi()
api.current = fake
const verified = { ...alex, emailVerified: true }
const codes = Array.from({ length: 10 }, (_, i) => `code${i}-abcde`)

beforeEach(() => {
  fake.reset()
  useState('account').value = verified
})

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>

function button(wrapper: Wrapper, label: string) {
  const found = wrapper.findAll('button').find((candidate: { text: () => string }) => candidate.text() === label)
  if (!found) throw new Error(`No "${label}" button`)
  return found
}

async function confirmPassword(wrapper: Wrapper): Promise<void> {
  await wrapper.get('input[type="password"]').setValue('correct horse battery')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('two-factor settings', () => {
  it('enrols an authenticator: password, QR code or app link, first code, then recovery codes shown once', async () => {
    fake.on('POST /api/me/two-factor/setup', () => ({ secret: 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', otpauthUri: 'otpauth://totp/BulkPilot:alex?secret=JBSWY3DPEHPK3PXP' }))
    fake.on('POST /api/me/two-factor/confirm', () => ({ recoveryCodes: codes }))
    fake.on('GET /api/me', () => ({ ...verified, twoFactorEnabled: true }))
    const wrapper = await mountSuspended(TwoFactorSettings, { props: { account: verified } })

    await button(wrapper, 'Activer').trigger('click')
    await confirmPassword(wrapper)

    expect(fake.calls.at(-1)?.body).toEqual({ password: 'correct horse battery' })
    expect(wrapper.get('svg[role="img"] path').attributes('d')).toMatch(/^M\d+ \d+h1v1h-1z/)
    expect(wrapper.get('a[href^="otpauth://"]').text()).toContain('Ouvrir dans mon application')
    expect(wrapper.findAll('[data-testid="totp-secret"] span').map(group => group.text())).toEqual(['JBSW', 'Y3DP', 'EHPK', '3PXP', 'JBSW', 'Y3DP', 'EHPK', '3PXP'])

    await wrapper.get('input[inputmode="numeric"]').setValue('123 456')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.findAll('[data-testid="recovery-codes"] li')).toHaveLength(10)
    expect(useState('account').value).toMatchObject({ twoFactorEnabled: true })
  })

  it('shows a wrong code next to the code field, and a wrong password on the password field', async () => {
    fake.on('POST /api/me/two-factor/setup', () => ({ secret: 'JBSWY3DPEHPK3PXP', otpauthUri: 'otpauth://totp/x' }))
    fake.on('POST /api/me/two-factor/confirm', () => {
      throw apiError(422, 'identity.invalid_second_factor_code')
    })
    const wrapper = await mountSuspended(TwoFactorSettings, { props: { account: verified } })
    await button(wrapper, 'Activer').trigger('click')
    await confirmPassword(wrapper)

    await wrapper.get('input[inputmode="numeric"]').setValue('000000')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const field = wrapper.get('input[inputmode="numeric"]')
    expect(field.attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Code invalide ou déjà utilisé')
  })

  it('forgets the last error when cancelled', async () => {
    fake.on('POST /api/me/two-factor/setup', () => {
      throw apiError(422, 'identity.wrong_current_password')
    })
    const wrapper = await mountSuspended(TwoFactorSettings, { props: { account: verified } })

    await button(wrapper, 'Activer').trigger('click')
    await confirmPassword(wrapper)
    expect(wrapper.text()).toContain('Le mot de passe actuel est incorrect.')

    await button(wrapper, 'Annuler').trigger('click')
    expect(wrapper.text()).not.toContain('Le mot de passe actuel est incorrect.')
  })

  it('points to the missing prerequisite instead of a dead end', async () => {
    const unverified = await mountSuspended(TwoFactorSettings, { props: { account: alex } })
    expect(unverified.get('a[href="/reglages/compte#email"]').text()).toBe('Renvoyer le lien')

    const passwordless = await mountSuspended(TwoFactorSettings, { props: { account: { ...verified, hasPassword: false } } })
    expect(passwordless.get('a[href="/mot-de-passe-oublie"]').text()).toBe('Définir un mot de passe')
  })

  it('regenerates recovery codes and disables two-factor with the password', async () => {
    const enabled = { ...verified, twoFactorEnabled: true }
    fake.on('POST /api/me/two-factor/recovery-codes', () => ({ recoveryCodes: codes }))
    fake.on('POST /api/me/two-factor/disable', () => undefined)
    fake.on('GET /api/me', () => verified)
    const wrapper = await mountSuspended(TwoFactorSettings, { props: { account: enabled } })

    await button(wrapper, 'Nouveaux codes de secours').trigger('click')
    await confirmPassword(wrapper)
    expect(wrapper.findAll('[data-testid="recovery-codes"] li')).toHaveLength(10)
  })

  it('disables two-factor with the password', async () => {
    fake.on('POST /api/me/two-factor/disable', () => undefined)
    fake.on('GET /api/me', () => verified)
    const wrapper = await mountSuspended(TwoFactorSettings, { props: { account: { ...verified, twoFactorEnabled: true } } })

    await button(wrapper, 'Désactiver').trigger('click')
    await confirmPassword(wrapper)

    expect(fake.calls.find(call => call.route === 'POST /api/me/two-factor/disable')?.body).toEqual({ password: 'correct horse battery' })
    expect(useState('account').value).toMatchObject({ twoFactorEnabled: false })
  })
})

describe('recovery codes panel', () => {
  it('only lets the user move on once the codes were copied or downloaded', async () => {
    const writeText = vi.fn(async () => {})
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:codes')
    const wrapper = await mountSuspended(RecoveryCodesPanel, { props: { codes: ['aaaaa-11111'] } })

    expect(button(wrapper, 'J’ai enregistré mes codes').attributes('disabled')).toBeDefined()
    await button(wrapper, 'Copier').trigger('click')
    await flushPromises()
    expect(writeText).toHaveBeenCalledWith(expect.stringContaining('aaaaa-11111'))
    expect(wrapper.get('[data-testid="recovery-feedback"][role="status"]').text()).toBe('Codes copiés.')

    await button(wrapper, 'J’ai enregistré mes codes').trigger('click')
    expect(wrapper.emitted('done')).toHaveLength(1)
  })

  it('explains when the clipboard is not available and offers the download', async () => {
    Object.defineProperty(navigator, 'clipboard', { value: { writeText: vi.fn(async () => Promise.reject(new Error('denied'))) }, configurable: true })
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:codes')
    const wrapper = await mountSuspended(RecoveryCodesPanel, { props: { codes: ['aaaaa-11111'] } })

    await button(wrapper, 'Copier').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-testid="recovery-feedback"][role="status"]').text()).toContain('Copie impossible')
    expect(button(wrapper, 'J’ai enregistré mes codes').attributes('disabled')).toBeDefined()

    await button(wrapper, 'Télécharger').trigger('click')
    expect(button(wrapper, 'J’ai enregistré mes codes').attributes('disabled')).toBeUndefined()
  })
})
