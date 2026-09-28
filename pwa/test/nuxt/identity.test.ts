import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DeviceList from '~/features/identity/components/DeviceList.vue'
import EmailStatus from '~/features/identity/components/EmailStatus.vue'
import PasswordChangeForm from '~/features/identity/components/PasswordChangeForm.vue'
import ProfileForm from '~/features/identity/components/ProfileForm.vue'
import SignInPage from '~/pages/connexion.vue'
import RegisterPage from '~/pages/inscription.vue'
import ForgotPage from '~/pages/mot-de-passe-oublie.vue'
import ResetPage from '~/pages/reinitialiser-mot-de-passe.vue'
import VerifyPage from '~/pages/verifier-email.vue'
import { alex, apiError, createFakeApi } from '../support/fakeApi'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))

mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)

const fake = createFakeApi()
api.current = fake

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  useState('account').value = undefined
  clearNuxtData()
})

async function submit(wrapper: Awaited<ReturnType<typeof mountSuspended>>): Promise<void> {
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('sign-in page', () => {
  it('signs in and goes where the visitor was heading', async () => {
    fake.on('POST /api/auth/sign-in', () => ({ twoFactorRequired: false, account: alex }))
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion?redirect=/training' })

    await wrapper.get('input[type="email"]').setValue('alex@example.com')
    await wrapper.get('input[type="password"]').setValue('correct horse battery')
    await submit(wrapper)

    expect(fake.calls.at(-1)).toMatchObject({ route: 'POST /api/auth/sign-in', body: { email: 'alex@example.com', password: 'correct horse battery' } })
    expect(useState('account').value).toEqual(alex)
    expect(navigate).toHaveBeenCalledWith('/training')
  })

  it('asks for the second factor, then signs in with it', async () => {
    fake.on('POST /api/auth/sign-in', () => ({ twoFactorRequired: true, account: null }))
    fake.on('POST /api/auth/sign-in/second-factor', () => ({ twoFactorRequired: false, account: alex }))
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion' })

    await submit(wrapper)
    expect(useState('account').value, 'the password alone does not sign in').not.toEqual(alex)
    expect(wrapper.text()).toContain('code à 6 chiffres')

    await wrapper.get('input').setValue('123456')
    await submit(wrapper)

    expect(fake.calls.at(-1)).toMatchObject({ route: 'POST /api/auth/sign-in/second-factor', body: { code: '123456' } })
    expect(useState('account').value).toEqual(alex)
    expect(navigate).toHaveBeenCalledWith('/')
  })

  it('offers recovery codes and a way back to the password step', async () => {
    fake.on('POST /api/auth/sign-in', () => ({ twoFactorRequired: true, account: null }))
    fake.on('POST /api/auth/sign-in/second-factor', () => {
      throw apiError(422, 'identity.invalid_second_factor_code')
    })
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion' })
    await submit(wrapper)

    await wrapper.findAll('button').find(button => button.text() === 'Utiliser un code de secours')!.trigger('click')
    expect(wrapper.text()).toContain('codes de secours')
    await wrapper.get('input').setValue('aaaaa-11111')
    await submit(wrapper)
    expect(wrapper.get('[role="alert"]').text()).toContain('Code invalide ou déjà utilisé.')

    await wrapper.findAll('button').find(button => button.text() === 'Retour')!.trigger('click')
    expect(wrapper.find('input[type="password"]').exists()).toBe(true)
    expect(wrapper.find('[role="alert"]').exists(), 'the previous error is not carried over').toBe(false)
  })

  it('explains wrong credentials and stays on the page', async () => {
    fake.on('POST /api/auth/sign-in', () => {
      throw apiError(401, 'identity.invalid_credentials')
    })
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion' })

    await submit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Email ou mot de passe incorrect.')
    expect(navigate).not.toHaveBeenCalled()
  })
})

describe('registration page', () => {
  it('creates the account and goes home', async () => {
    fake.on('POST /api/auth/register', () => alex)
    const wrapper = await mountSuspended(RegisterPage, { route: '/inscription' })

    const inputs = wrapper.findAll('input')
    await inputs[0]!.setValue('Alex')
    await inputs[1]!.setValue('alex@example.com')
    await inputs[2]!.setValue('correct horse battery')
    await submit(wrapper)

    expect(fake.calls.at(-1)?.body).toEqual({ firstName: 'Alex', email: 'alex@example.com', password: 'correct horse battery' })
    expect(navigate).toHaveBeenCalledWith('/')
  })

  it('shows field errors next to the fields', async () => {
    fake.on('POST /api/auth/register', () => {
      throw apiError(422, null, [{ propertyPath: 'password', message: 'Trop court.' }])
    })
    const wrapper = await mountSuspended(RegisterPage, { route: '/inscription' })

    await submit(wrapper)

    const password = wrapper.get('input[type="password"]')
    expect(password.attributes('aria-invalid')).toBe('true')
    expect(wrapper.get(`#${password.attributes('aria-describedby')!.split(' ').at(-1)}`).text()).toBe('Trop court.')
  })
})

describe('password recovery pages', () => {
  it('confirms a reset request without telling whether the account exists', async () => {
    fake.on('POST /api/auth/password-reset-request', () => undefined)
    const wrapper = await mountSuspended(ForgotPage, { route: '/mot-de-passe-oublie' })

    await wrapper.get('input').setValue('who@example.com')
    await submit(wrapper)

    expect(wrapper.get('[role="status"]').text()).toContain('Si un compte avec une adresse confirmée existe pour who@example.com')
  })

  it('sets the new password with the token from the link', async () => {
    fake.on('POST /api/auth/password-reset', () => undefined)
    const wrapper = await mountSuspended(ResetPage, { route: '/reinitialiser-mot-de-passe?token=abc.def' })

    await wrapper.get('input').setValue('a brand new passphrase')
    await submit(wrapper)

    expect(fake.calls.at(-1)?.body).toEqual({ token: 'abc.def', newPassword: 'a brand new passphrase' })
    expect(wrapper.get('[role="status"]').text()).toContain('tous tes appareils ont été déconnectés')
  })

  it('says straight away when the link has no token, and offers a new one', async () => {
    const wrapper = await mountSuspended(ResetPage, { route: '/reinitialiser-mot-de-passe' })

    expect(wrapper.find('form').exists()).toBe(false)
    expect(wrapper.get('a[href="/mot-de-passe-oublie"]').text()).toBe('Demander un nouveau lien')
  })

  it('explains a dead link', async () => {
    fake.on('POST /api/auth/password-reset', () => {
      throw apiError(422, 'identity.invalid_token')
    })
    const wrapper = await mountSuspended(ResetPage, { route: '/reinitialiser-mot-de-passe?token=old' })

    await wrapper.get('input').setValue('a brand new passphrase')
    await submit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Ce lien est invalide, expiré ou a déjà été utilisé.')
    expect(wrapper.find('form').exists(), 'no point retyping a password for a dead link').toBe(false)
  })
})

describe('email verification page', () => {
  it('redeems the link on arrival', async () => {
    fake.on('POST /api/auth/email-verification', () => undefined)
    const wrapper = await mountSuspended(VerifyPage, { route: '/verifier-email?token=abc.def' })
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/auth/email-verification')?.body).toEqual({ token: 'abc.def' })
    expect(wrapper.text()).toContain('Ton adresse email est confirmée.')
  })

  it('offers a way out when the link is dead', async () => {
    fake.on('POST /api/auth/email-verification', () => {
      throw apiError(422, 'identity.invalid_token')
    })
    const wrapper = await mountSuspended(VerifyPage, { route: '/verifier-email?token=old' })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Ce lien est invalide, expiré ou a déjà été utilisé.')
  })
})

describe('settings sections', () => {
  it('saves the profile with empty fields sent as null', async () => {
    fake.on('PUT /api/me/profile', body => ({ ...alex, ...(body as object) }))
    const wrapper = await mountSuspended(ProfileForm, { props: { account: alex } })

    await wrapper.get('input[type="number"]').setValue('178')
    await wrapper.get('select').setValue('male')
    await submit(wrapper)

    expect(fake.calls.at(-1)?.body).toEqual({ firstName: 'Alex', birthDate: null, sex: 'male', heightCm: 178 })
    expect(wrapper.get('[role="status"]').text()).toBe('Profil enregistré.')
  })

  it('resends the verification link while the email is unconfirmed', async () => {
    fake.on('POST /api/me/email-verification', () => undefined)
    const wrapper = await mountSuspended(EmailStatus, { props: { account: alex } })

    expect(wrapper.text()).toContain('À confirmer')
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toBe('Un nouveau lien vient d’être envoyé à alex@example.com.')
    expect(wrapper.find('button').exists(), 'no double send').toBe(false)
  })

  it('shows a confirmed email without any action', async () => {
    const wrapper = await mountSuspended(EmailStatus, { props: { account: { ...alex, emailVerified: true } } })

    expect(wrapper.text()).toContain('Confirmée')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('changes the password and clears the fields', async () => {
    fake.on('POST /api/me/password', () => undefined)
    const wrapper = await mountSuspended(PasswordChangeForm)
    const [current, next] = wrapper.findAll('input')

    await current!.setValue('correct horse battery')
    await next!.setValue('a brand new passphrase')
    await submit(wrapper)

    expect(fake.calls.at(-1)?.body).toEqual({ currentPassword: 'correct horse battery', newPassword: 'a brand new passphrase' })
    expect((current!.element as HTMLInputElement).value).toBe('')
    expect(wrapper.get('[role="status"]').text()).toContain('autres appareils ont été déconnectés')
  })

  it('reports a wrong current password', async () => {
    fake.on('POST /api/me/password', () => {
      throw apiError(422, 'identity.wrong_current_password')
    })
    const wrapper = await mountSuspended(PasswordChangeForm)

    await submit(wrapper)

    expect(wrapper.get('[role="alert"]').text()).toBe('Le mot de passe actuel est incorrect.')
  })

  it('lists devices and signs another one out', async () => {
    const sessions = [
      { id: 's1', deviceLabel: 'Firefox · Linux', openedAt: '2026-09-28T10:00:00+00:00', lastSeenAt: '2026-09-28T10:00:00+00:00', current: true },
      { id: 's2', deviceLabel: 'Safari · iOS', openedAt: '2026-09-27T10:00:00+00:00', lastSeenAt: '2026-09-27T10:00:00+00:00', current: false },
    ]
    fake.on('GET /api/me/sessions', () => sessions.filter(session => session.id !== 'gone'))
    fake.on('DELETE /api/me/sessions/{id}', (_, params) => {
      sessions[1]!.id = 'gone'
      expect(params?.path).toEqual({ id: 's2' })
    })
    const wrapper = await mountSuspended(DeviceList)
    await flushPromises()

    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.text()).toContain('Cet appareil')
    expect(wrapper.findAll('button')).toHaveLength(1)
    await wrapper.get('button[aria-label="Déconnecter Safari · iOS"]').trigger('click')
    await flushPromises()

    expect(wrapper.findAll('li')).toHaveLength(1)
  })
})

describe('external sign-in', () => {
  it('offers the enabled providers and keeps the destination', async () => {
    fake.on('GET /api/auth/providers', () => ({ providers: ['google'] }))
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion?redirect=/training' })
    await flushPromises()

    const link = wrapper.get('a[href^="/api/auth/oauth/"]')
    expect(link.text()).toBe('Continuer avec Google')
    expect(link.attributes('href')).toBe('/api/auth/oauth/google/start?redirect=%2Ftraining')
  })

  it('shows nothing when no provider is configured', async () => {
    fake.on('GET /api/auth/providers', () => ({ providers: [] }))
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion' })
    await flushPromises()

    expect(wrapper.find('a[href^="/api/auth/oauth/"]').exists()).toBe(false)
  })

  it('explains an error reported by the provider flow', async () => {
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion?oauth_error=identity.external_account_conflict' })

    expect(wrapper.get('[role="alert"]').text()).toContain('Un compte non confirmé utilise déjà cette adresse')
  })

  it('goes straight to the second factor when the provider flow requires it', async () => {
    fake.on('POST /api/auth/sign-in/second-factor', () => ({ twoFactorRequired: false, account: alex }))
    const wrapper = await mountSuspended(SignInPage, { route: '/connexion?second_factor=1&redirect=/coach' })

    await wrapper.get('input').setValue('123456')
    await submit(wrapper)

    expect(navigate).toHaveBeenCalledWith('/coach')
  })
})
