import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DataPage from '~/pages/reglages/donnees.vue'
import DeletionPage from '~/pages/reglages/suppression.vue'
import SignInPage from '~/pages/connexion.vue'
import DeletionCancelledNotice from '~/components/shell/DeletionCancelledNotice.vue'
import { button, field } from '../support/dom'
import { accountExport, DATA_POLICY, DOMAINS } from '../support/data'
import { alex, apiError, createFakeApi } from '../support/fakeApi'

const { api, navigate, forgetDevice, download } = vi.hoisted(() => ({
  api: { current: null as ReturnType<typeof createFakeApi> | null },
  navigate: vi.fn(),
  forgetDevice: vi.fn(async () => {}),
  download: vi.fn(),
}))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
vi.mock('~/services/offline/owner', async importOriginal => ({ ...(await importOriginal<object>()), forgetDevice }))
vi.mock('~/services/download', () => ({ download }))
const fake = createFakeApi()
api.current = fake

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  forgetDevice.mockReset()
  forgetDevice.mockImplementation(async () => {})
  download.mockReset()
  clearNuxtData()
  useState('account').value = alex
  useState('account-deletion-cancelled').value = false
  fake.on('GET /api/exports/domains', () => DOMAINS)
  fake.on('GET /api/me/data-policy', () => DATA_POLICY)
  fake.on('GET /api/auth/providers', () => ({ providers: ['google'] }))
  fake.on('GET /api/exports', () => [])
})
afterEach(() => {
  vi.useRealTimers()
})

async function open(page: Parameters<typeof mountSuspended>[0], route?: string) {
  const wrapper = await mountSuspended(page, { attachTo: document.body, ...(route && { route }) })
  await flushPromises()
  return wrapper
}

describe('exporting one’s data', () => {
  it('asks for everything by default, and shows it being made', async () => {
    fake.on('GET /api/exports', () => [])
    fake.on('POST /api/exports', () => accountExport())
    const wrapper = await open(DataPage)
    expect(wrapper.text()).toContain('Aucun export pour l’instant')
    expect(wrapper.findAll('input[type="checkbox"]').every(box => (box.element as HTMLInputElement).checked)).toBe(true)

    await button(wrapper, 'Préparer mon export').trigger('click')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'POST /api/exports')?.body).toEqual({})
    expect(wrapper.text()).toContain('En préparation')
    expect(wrapper.get('[data-testid="status"]').text()).toContain('Export demandé')
    wrapper.unmount()
  })

  it('sends only the period and the domains chosen', async () => {
    fake.on('GET /api/exports', () => [])
    fake.on('POST /api/exports', () => accountExport())
    const wrapper = await open(DataPage)

    await field(wrapper, 'Du').setValue('2026-09-01')
    await field(wrapper, 'Training').setValue(false)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'POST /api/exports')?.body).toEqual({ from: '2026-09-01', domains: ['identity', 'nutrition'] })
    wrapper.unmount()
  })

  it('refuses nothing chosen, or a period that ends before it starts', async () => {
    fake.on('GET /api/exports', () => [])
    const wrapper = await open(DataPage)

    for (const domain of DOMAINS) await field(wrapper, domain.title).setValue(false)
    await field(wrapper, 'Du').setValue('2026-09-30')
    await field(wrapper, 'Au').setValue('2026-09-01')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Choisis au moins une donnée à exporter.')
    expect(wrapper.text()).toContain('La fin de la période vient avant son début.')
    expect(document.activeElement, 'the first field to fix has the focus').toBe(field(wrapper, 'Au').element)
    const box = field(wrapper, 'Compte')
    expect(box.attributes('aria-invalid')).toBe('true')
    expect(box.attributes('aria-describedby')).toContain('export-domains-error')
    expect(fake.calls.some(call => call.route === 'POST /api/exports')).toBe(false)
    wrapper.unmount()
  })

  it('does not offer another one while one is being made', async () => {
    fake.on('GET /api/exports', () => [accountExport({ status: 'queued' })])
    const wrapper = await open(DataPage)
    const submit = button(wrapper, 'Préparer mon export')
    expect(submit.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Un export est déjà en préparation')
    wrapper.unmount()
  })

  it('says which data the period narrows, and how long an archive stays', async () => {
    const wrapper = await open(DataPage)
    expect(wrapper.text()).toContain('La période s’applique à\u00A0: Nutrition, Training.')
    expect(wrapper.text()).toContain('reste téléchargeable 24\u00A0heures')
    wrapper.unmount()
  })

  it('says nothing of a duration the server did not give', async () => {
    // A failing server answering without the field: no « téléchargeable undefined heure ».
    fake.on('GET /api/me/data-policy', () => ({ ...DATA_POLICY, exportAvailableHours: undefined }))
    clearNuxtData('data-policy')
    const wrapper = await open(DataPage)
    expect(wrapper.text()).not.toContain('undefined')
    expect(wrapper.text()).not.toContain('reste téléchargeable')
    wrapper.unmount()
  })

  it('looks again until it is ready, says so, then stops', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    let state: 'queued' | 'ready' = 'queued'
    fake.on('GET /api/exports', () => [accountExport({ status: state, ...(state === 'ready' && { expiresAt: '2026-10-01T08:05:00+00:00', downloadable: true }) })])
    const wrapper = await open(DataPage)
    const reads = () => fake.calls.filter(call => call.route === 'GET /api/exports').length
    expect(reads()).toBe(1)

    await vi.advanceTimersByTimeAsync(3000)
    expect(reads()).toBe(2)
    state = 'ready'
    // Then a little more slowly (4,5 s).
    await vi.advanceTimersByTimeAsync(4500)
    await flushPromises()
    expect(reads()).toBe(3)
    expect(wrapper.get('[data-testid="status"]').text()).toContain('Ton export est prêt')
    await vi.advanceTimersByTimeAsync(10_000)
    expect(reads(), 'nothing left to wait for').toBe(3)
    wrapper.unmount()
  })

  it('stops looking when it failed, and says so', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    let state: 'queued' | 'failed' = 'queued'
    fake.on('GET /api/exports', () => [accountExport({ status: state })])
    const wrapper = await open(DataPage)
    state = 'failed'
    await vi.advanceTimersByTimeAsync(3000)
    await flushPromises()
    expect(wrapper.text()).toContain('La préparation a échoué')
    expect(wrapper.get('[data-testid="status"]').text()).toContain('a échoué')
    await vi.advanceTimersByTimeAsync(10_000)
    expect(fake.calls.filter(call => call.route === 'GET /api/exports')).toHaveLength(2)
    wrapper.unmount()
  })

  it('downloads a ready one through a fresh link', async () => {
    const ready = accountExport({ status: 'ready', expiresAt: '2026-10-01T08:05:00+00:00', downloadable: true })
    fake.on('GET /api/exports', () => [ready])
    fake.on('POST /api/exports/{id}/download', () => ({ url: 'https://s3/export.zip?sig' }))
    const wrapper = await open(DataPage)

    await wrapper.findAll('button').find(b => b.text().startsWith('Télécharger'))!.trigger('click')
    await flushPromises()
    expect(fake.calls).toContainEqual({ route: 'POST /api/exports/{id}/download', body: undefined, params: { path: { id: ready.id } } })
    expect(download).toHaveBeenCalledWith('https://s3/export.zip?sig')
    expect(navigate, 'the app is not navigated away').not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Exports récents')
    wrapper.unmount()
  })

  it('reads the list again when the export went meanwhile, not after any error', async () => {
    const ready = accountExport({ status: 'ready', expiresAt: '2026-10-01T08:05:00+00:00', downloadable: true })
    fake.on('GET /api/exports', () => [ready])
    let error = apiError(409, 'export.not_ready')
    fake.on('POST /api/exports/{id}/download', () => {
      throw error
    })
    const wrapper = await open(DataPage)
    const reads = () => fake.calls.filter(call => call.route === 'GET /api/exports').length
    const downloadButton = () => wrapper.findAll('button').find(b => b.text().startsWith('Télécharger'))!

    await downloadButton().trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('Cet export n’est pas encore prêt.')
    expect(reads()).toBe(2)

    error = apiError(503, null)
    await downloadButton().trigger('click')
    await flushPromises()
    expect(reads(), 'a network failure is no reason to read the list').toBe(2)
    expect(download).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('never looks again once the page is gone, even with a read on its way', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    let answer: (value: unknown) => void = () => {}
    let first = true
    fake.on('GET /api/exports', () => {
      if (first) {
        first = false
        return [accountExport({ status: 'queued' })]
      }
      return new Promise((resolve) => {
        answer = resolve
      })
    })
    const wrapper = await open(DataPage)
    await vi.advanceTimersByTimeAsync(3000)
    wrapper.unmount()
    answer([accountExport({ status: 'queued' })])
    await flushPromises()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(fake.calls.filter(call => call.route === 'GET /api/exports')).toHaveLength(2)
  })

  it('pauses while the page is hidden, and looks again once shown', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    fake.on('GET /api/exports', () => [accountExport({ status: 'queued' })])
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden')
    const wrapper = await open(DataPage)
    await vi.advanceTimersByTimeAsync(60_000)
    expect(fake.calls.filter(call => call.route === 'GET /api/exports')).toHaveLength(1)

    visibility.mockReturnValue('visible')
    document.dispatchEvent(new Event('visibilitychange'))
    await vi.advanceTimersByTimeAsync(3000)
    expect(fake.calls.filter(call => call.route === 'GET /api/exports')).toHaveLength(2)
    wrapper.unmount()
  })

  it('looks again after a failed read', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    let reads = 0
    fake.on('GET /api/exports', () => {
      reads++
      if (reads === 2) throw apiError(503, null)
      return [accountExport({ status: 'queued' })]
    })
    const wrapper = await open(DataPage)
    await vi.advanceTimersByTimeAsync(3000)
    expect(reads).toBe(2)
    await vi.advanceTimersByTimeAsync(4500)
    expect(reads, 'tried again').toBe(3)
    wrapper.unmount()
  })
})

describe('deleting one’s account', () => {
  it('asks for the second factor only when it is on', async () => {
    const without = await open(DeletionPage)
    expect(without.text()).not.toContain('Code de double authentification')
    without.unmount()

    useState('account').value = { ...alex, twoFactorEnabled: true }
    const withIt = await open(DeletionPage)
    expect(withIt.text()).toContain('Code de double authentification')
    withIt.unmount()
  })

  it('asks an account without a password to sign in again first, and sends an empty one', async () => {
    useState('account').value = { ...alex, hasPassword: false }
    fake.on('POST /api/me/deletion', () => {
      throw apiError(422, 'identity.reauthentication_required')
    })
    const wrapper = await open(DeletionPage)
    expect(wrapper.find('input[type="password"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('reconnecte-toi juste avant (moins de 10\u00A0minutes)')
    const again = wrapper.findAll('a').find(link => link.text() === 'Me reconnecter avec Google')!
    expect(again.attributes('href')).toBe('/api/auth/oauth/google/start?redirect=%2Freglages%2Fsuppression')

    await field(wrapper, 'Écris SUPPRIMER pour confirmer').setValue('SUPPRIMER')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'POST /api/me/deletion')?.body).toEqual({ password: '' })
    expect(wrapper.text()).toContain('Ta dernière connexion est trop ancienne')
    wrapper.unmount()
  })

  it('says a wrong second factor code', async () => {
    useState('account').value = { ...alex, twoFactorEnabled: true }
    fake.on('POST /api/me/deletion', () => {
      throw apiError(422, 'identity.invalid_second_factor_code')
    })
    const wrapper = await open(DeletionPage)
    await field(wrapper, 'Ton mot de passe').setValue('correct horse battery')
    await field(wrapper, 'Code de double authentification').setValue('000000')
    await field(wrapper, 'Écris SUPPRIMER pour confirmer').setValue('SUPPRIMER')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).not.toBe('')
    expect(navigate).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('says when it would be erased, links the summary to the button, and offers the latest export', async () => {
    vi.useFakeTimers({ now: new Date('2026-09-30T10:00:00'), toFake: ['Date'] })
    const ready = accountExport({ status: 'ready', expiresAt: '2026-10-01T08:05:00+00:00', downloadable: true })
    fake.on('GET /api/exports', () => [ready])
    fake.on('POST /api/exports/{id}/download', () => ({ url: 'https://s3/export.zip?sig' }))
    const wrapper = await open(DeletionPage)
    expect(wrapper.text()).toContain('puis effacé le mercredi 7 octobre')
    expect(button(wrapper, 'Supprimer mon compte').attributes('aria-describedby')).toBe('deletion-consequences')
    expect(wrapper.text()).toContain('télécharge l’archive avant de confirmer')
    await wrapper.findAll('button').find(b => b.text().startsWith('Télécharger'))!.trigger('click')
    await flushPromises()
    expect(download).toHaveBeenCalledWith('https://s3/export.zip?sig')
    wrapper.unmount()
  })

  it('warns when the export is still being made', async () => {
    fake.on('GET /api/exports', () => [accountExport({ status: 'queued' })])
    const wrapper = await open(DeletionPage)
    expect(wrapper.text()).toContain('Ton export est encore en préparation')
    wrapper.unmount()
  })

  it('wants the word typed, and the password', async () => {
    const wrapper = await open(DeletionPage)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Saisis ton mot de passe.')
    expect(wrapper.text()).toContain('Écris SUPPRIMER pour confirmer.')
    expect(document.activeElement, 'the first field to fix has the focus').toBe(field(wrapper, 'Ton mot de passe').element)
    expect(fake.calls.some(call => call.route === 'POST /api/me/deletion')).toBe(false)
    wrapper.unmount()
  })

  it('closes the account, forgets the device and says when it will be erased', async () => {
    useState('account').value = { ...alex, twoFactorEnabled: true }
    vi.useFakeTimers({ now: new Date('2026-09-30T10:00:00'), toFake: ['Date'] })
    fake.on('POST /api/me/deletion', () => ({ deletionDueAt: '2026-10-07T08:00:00+00:00' }))
    const wrapper = await open(DeletionPage)

    await field(wrapper, 'Ton mot de passe').setValue('correct horse battery')
    await field(wrapper, 'Code de double authentification').setValue(' 123456 ')
    await field(wrapper, 'Écris SUPPRIMER pour confirmer').setValue('supprimer')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'POST /api/me/deletion')?.body).toEqual({ password: 'correct horse battery', code: '123456' })
    expect(forgetDevice).toHaveBeenCalled()
    expect(useState('account').value).toBeNull()
    expect(navigate).toHaveBeenCalledWith({ path: '/connexion', query: { suppression: '2026-10-07' } })
    wrapper.unmount()
  })

  it('still leaves for the sign-in page when the device could not be emptied', async () => {
    forgetDevice.mockRejectedValue(new Error('IndexedDB is gone'))
    fake.on('POST /api/me/deletion', () => ({ deletionDueAt: '2026-10-07T08:00:00+00:00' }))
    const wrapper = await open(DeletionPage)
    await field(wrapper, 'Ton mot de passe').setValue('correct horse battery')
    await field(wrapper, 'Écris SUPPRIMER pour confirmer').setValue('SUPPRIMER')
    const report = vi.spyOn(console, 'error').mockImplementation(() => {})
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(report).toHaveBeenCalled()
    report.mockRestore()
    expect(useState('account').value).toBeNull()
    expect(navigate).toHaveBeenCalledWith({ path: '/connexion', query: { suppression: '2026-10-07' } })
    wrapper.unmount()
  })

  it('keeps the account when the password is wrong', async () => {
    fake.on('POST /api/me/deletion', () => {
      throw apiError(422, 'identity.wrong_current_password')
    })
    const wrapper = await open(DeletionPage)
    await field(wrapper, 'Ton mot de passe').setValue('wrong')
    await field(wrapper, 'Écris SUPPRIMER pour confirmer').setValue('SUPPRIMER')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Le mot de passe actuel est incorrect.')
    expect(useState('account').value).toEqual(alex)
    expect(navigate).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('tells, on the sign-in page, when the account will be erased and how to keep it', async () => {
    useState('account').value = null
    const wrapper = await open(SignInPage, '/connexion?suppression=2026-10-07')
    expect(wrapper.text()).toContain('Ton compte sera supprimé le 7 octobre 2026 et tous tes appareils sont déconnectés.')
    expect(wrapper.text()).toContain('Te reconnecter d’ici là annule la suppression.')
    expect(document.activeElement?.textContent, 'said first').toContain('Ton compte sera supprimé')
    wrapper.unmount()
  })

  it('says once that signing in cancelled the deletion', async () => {
    useState('account').value = null
    fake.on('POST /api/auth/sign-in', () => ({ twoFactorRequired: false, account: alex, deletionCancelled: true }))
    const signIn = await open(SignInPage, '/connexion')
    await signIn.get('input[type="email"]').setValue('alex@example.com')
    await signIn.get('input[type="password"]').setValue('correct horse battery')
    await signIn.get('form').trigger('submit')
    await flushPromises()
    signIn.unmount()

    const main = document.createElement('main')
    main.id = 'main'
    main.tabIndex = -1
    document.body.append(main)
    const notice = await open(DeletionCancelledNotice)
    expect(notice.text()).toContain('La suppression de ton compte est annulée')
    expect(notice.get('[role="status"]').text(), 'announced').toBe('La suppression de ton compte est annulée.')
    await button(notice, 'Compris').trigger('click')
    await flushPromises()
    expect(notice.find('button').exists()).toBe(false)
    expect(document.activeElement, 'the page has the focus').toBe(main)
    notice.unmount()
    main.remove()
  })

  it('says it after a second-factor sign-in too, and forgets it with the account', async () => {
    useState('account').value = null
    fake.on('POST /api/auth/sign-in/second-factor', () => ({ account: alex, deletionCancelled: true }))
    const { completeSignIn, forget } = useAccount()
    await completeSignIn('123456')
    expect(useState('account-deletion-cancelled').value).toBe(true)
    forget()
    expect(useState('account-deletion-cancelled').value, 'not for the next account').toBe(false)
  })

  it('says it after a Google sign-in, from the redirect, and cleans the address', async () => {
    const router = useRouter()
    await router.push('/reglages?suppression_annulee=1&onglet=x')
    await flushPromises()
    expect(useState('account-deletion-cancelled').value).toBe(true)
    expect(navigate).toHaveBeenCalledWith({ path: '/reglages', query: { onglet: 'x' }, hash: '' }, { replace: true })
  })
})
