import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PhotosPage from '~/pages/progression/photos.vue'
import { localDay } from '~/services/calendar/days'
import { button, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { progressPhoto, sessions } from '../support/progression'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)
const fake = createFakeApi()
api.current = fake

beforeEach(() => {
  fake.reset()
  clearNuxtData()
  fake.on('GET /api/progression/photo-sessions', () => sessions)
  vi.stubGlobal('createImageBitmap', undefined)
  // A stand-in rather than the real URL changed in place: nothing is left behind for other files.
  vi.stubGlobal('URL', class extends URL {
    static override createObjectURL = vi.fn(() => 'blob:preview')
    static override revokeObjectURL = vi.fn()
  })
})
afterEach(() => {
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

async function openPage(options: { attachTo?: HTMLElement } = {}) {
  const wrapper = await mountSuspended(PhotosPage, { route: '/progression/photos', ...options })
  await flushPromises()
  return wrapper
}

describe('progress photos', () => {
  it('sends a photo with its day and pose', async () => {
    fake.on('POST /api/progression/photos', () => progressPhoto(localDay(), 'side'))
    const wrapper = await openPage({ attachTo: document.body })
    expect(button(wrapper, 'Ajouter la photo').attributes('disabled'), 'nothing picked yet').toBeDefined()

    const input = wrapper.get('input[type="file"]')
    const file = new File(['jpeg'], 'IMG_2041.jpg', { type: 'image/jpeg' })
    Object.defineProperty(input.element, 'files', { value: [file] })
    await input.trigger('change')
    expect(wrapper.get('img[alt="Aperçu de la photo choisie"]').attributes('src')).toBe('blob:preview')
    await wrapper.get('input[name="photo-pose"][value="side"]').setValue(true)
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const form = fake.calls.find(call => call.route === 'POST /api/progression/photos')?.body as FormData
    // The browser could not redraw it: it goes as it was, name and extension included.
    expect([form.get('day'), form.get('pose'), (form.get('file') as File).name]).toEqual([localDay(), 'side', 'IMG_2041.jpg'])
    expect(wrapper.text()).toContain('Photo ajoutée (profil).')
    expect(fake.calls.filter(call => call.route === 'GET /api/progression/photo-sessions')).toHaveLength(2)
    expect((wrapper.get('input[name="photo-pose"][value="back"]').element as HTMLInputElement).checked, 'the next pose').toBe(true)
    expect(document.activeElement, 'back on the file picker').toBe(input.element)
    wrapper.unmount()
  })

  it('keeps the choice as it is while the photo is on its way', async () => {
    let answer: (photo: unknown) => void = () => {}
    fake.on('POST /api/progression/photos', () => new Promise((resolve) => {
      answer = resolve
    }))
    const wrapper = await openPage()
    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [new File(['jpeg'], 'a.jpg', { type: 'image/jpeg' })] })
    await input.trigger('change')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(input.attributes('disabled'), 'inside a disabled fieldset').toBeUndefined()
    expect(wrapper.get('form fieldset').attributes('disabled')).toBeDefined()
    expect(button(wrapper, 'Ajouter la photo').attributes('aria-disabled')).toBe('true')
    await wrapper.get('form').trigger('submit')
    expect(fake.calls.filter(call => call.route === 'POST /api/progression/photos')).toHaveLength(1)
    answer(progressPhoto(localDay(), 'front'))
    await flushPromises()
  })

  it('says why a photo was refused', async () => {
    fake.on('POST /api/progression/photos', () => {
      throw apiError(422, 'media.invalid_photo')
    })
    const wrapper = await openPage()
    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'x.gif')] })
    await input.trigger('change')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('JPEG, PNG ou WebP')
  })

  it('shows each session with its weight and measurements, and a photo to download or delete', async () => {
    fake.on('DELETE /api/progression/photos/{id}', () => null)
    const wrapper = await openPage({ attachTo: document.body })

    expect(wrapper.text()).toContain('Poids\u00A0: 51,2\u00A0kg (pesée du 27 septembre 2026)')
    expect(wrapper.text()).toContain('Poids\u00A0: 45,0\u00A0kg')
    expect(wrapper.text()).toContain('Mensurations\u00A0: Bras gauche 30,5\u00A0cm, Taille 70,0\u00A0cm (mesurées le 20 septembre 2026)')
    expect(wrapper.text()).toContain('Mensurations\u00A0: Taille 68,0\u00A0cm')
    const thumbnail = button(wrapper, 'Dos, 28 septembre 2026')
    expect(thumbnail.get('img').attributes()).toMatchObject({ src: sessions[0]!.photos[1]!.thumbnailUrl, alt: '' })

    await thumbnail.trigger('click')
    await flushPromises()
    const dialog = wrapper.get('dialog[open]')
    expect(dialog.get('img').attributes('src')).toBe(sessions[0]!.photos[1]!.url)
    expect(dialog.get('a').attributes('href')).toBe(sessions[0]!.photos[1]!.downloadUrl)

    await button(wrapper, 'Supprimer la photo', 'dialog[open]').trigger('click')
    await button(wrapper, 'Confirmer (elle sera effacée)', 'dialog[open]').trigger('click')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'DELETE /api/progression/photos/{id}')?.params).toEqual({ path: { id: sessions[0]!.photos[1]!.id } })
    expect(wrapper.findAll('[role="status"]').map(status => status.text())).toContain('Photo supprimée.')
    expect(document.activeElement?.hasAttribute('data-photo'), 'on another photo').toBe(true)
    wrapper.unmount()
  })

  it('says in the open photo why it could not be deleted', async () => {
    fake.on('DELETE /api/progression/photos/{id}', () => {
      throw apiError(503, null)
    })
    const wrapper = await openPage()
    await button(wrapper, 'Face, 1 juin 2026').trigger('click')
    await flushPromises()
    await button(wrapper, 'Supprimer la photo', 'dialog[open]').trigger('click')
    await button(wrapper, 'Confirmer (elle sera effacée)', 'dialog[open]').trigger('click')
    await flushPromises()

    expect(wrapper.find('dialog[open] [role="alert"]').exists()).toBe(true)
  })

  it('compares the first session with the latest, side by side or with a slider', async () => {
    const wrapper = await openPage()
    const compare = wrapper.findAll('section').find(section => section.text().startsWith('Comparer'))!

    expect(compare.findAll('figure img').map(img => img.attributes('alt'))).toEqual(['Face, 1 juin 2026 — 45,0\u00A0kg', 'Face, 28 septembre 2026 — 51,2\u00A0kg'])
    expect(compare.text()).toContain('Poids\u00A0: +6,2\u00A0kg')
    expect(compare.text()).toContain('Taille\u00A0: +2,0\u00A0cm (70,0\u00A0cm)')
    expect(compare.findAll('input[name="compare-pose"]').map(input => (input.element as HTMLInputElement).value), 'poses both days have').toEqual(['front'])

    await compare.get('input[name="compare-mode"][value="slider"]').setValue(true)
    const slider = field(wrapper, 'Curseur (à gauche l’avant, à droite l’après)')
    await slider.setValue('30')
    expect(compare.findAll('img')[1]!.attributes('style')).toContain('inset(0 0 0 30%)')
    expect(slider.attributes('aria-valuetext')).toBe('30\u00A0% de l’avant visible')

    await field(wrapper, 'Avant').setValue('2026-09-28')
    expect(compare.text()).not.toContain('Poids\u00A0: +')
  })

  it('says when there is nothing yet, or retries a failed load', async () => {
    fake.on('GET /api/progression/photo-sessions', () => [])
    const empty = await openPage()
    expect(empty.text()).toContain('Aucune photo')
    expect(empty.text()).not.toContain('Comparer')

    clearNuxtData()
    fake.on('GET /api/progression/photo-sessions', () => {
      throw apiError(503, null)
    })
    expect(button(await openPage(), 'Réessayer')).toBeDefined()
  })

  const expiringAt = (expiresAt: string) => [{ ...sessions[1]!, photos: [{ ...sessions[1]!.photos[0]!, expiresAt }] }]
  const loads = () => fake.calls.filter(call => call.route === 'GET /api/progression/photo-sessions').length

  it('asks for new links before the ones shown expire, and again after a failure', async () => {
    vi.useFakeTimers({ now: new Date('2026-09-29T08:00:00Z'), toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    fake.on('GET /api/progression/photo-sessions', () => expiringAt('2026-09-29T08:05:00Z'))
    await openPage()
    fake.on('GET /api/progression/photo-sessions', () => {
      throw apiError(503, null)
    })

    await vi.advanceTimersByTimeAsync(4 * 60_000 + 31_000)
    expect(loads()).toBe(2)
    await vi.advanceTimersByTimeAsync(20_000)
    expect(loads(), 'tried again 20 s later').toBe(3)
    await vi.advanceTimersByTimeAsync(40_000)
    expect(loads(), 'then 40 s later').toBe(4)
  })

  it('looks at the links again when the tab comes back', async () => {
    vi.useFakeTimers({ now: new Date('2026-09-29T08:00:00Z'), toFake: ['setTimeout', 'clearTimeout', 'Date'] })
    fake.on('GET /api/progression/photo-sessions', () => expiringAt('2026-09-29T08:05:00Z'))
    const wrapper = await openPage()

    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(loads(), 'still valid for a while').toBe(1)
    vi.setSystemTime(new Date('2026-09-29T08:06:00Z'))
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(loads(), 'expired while the tab slept').toBe(2)
    wrapper.unmount()
  })
})
