import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import NotificationsPage from '~/pages/notifications/index.vue'
import SettingsPage from '~/pages/notifications/reglages.vue'
import { button, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { notification, notificationSettings } from '../support/notifications'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  useState('notifications-unread').value = 0
  fake.on('GET /api/notifications/unread', () => ({ count: 0 }))
})

async function open(page: typeof NotificationsPage) {
  const wrapper = await mountSuspended(page, { attachTo: document.body })
  await flushPromises()
  return wrapper
}

describe('the notifications', () => {
  it('lists them, the unread ones said so in words', async () => {
    const unread = notification()
    const read = notification({ title: 'Nouveau record', category: 'record', readAt: '2026-09-30T19:00:00+00:00', link: '/training/seances/x' })
    fake.on('GET /api/notifications', () => [unread, read])
    const wrapper = await open(NotificationsPage)

    const items = wrapper.findAll('li')
    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('Non lue\u00A0: Calories très insuffisantes')
    expect(items[0]!.attributes('data-unread')).toBe('true')
    expect(items[1]!.text()).not.toContain('Non lue')
    // Each category's own icon, in its tint, beside the words that say what it is.
    expect(items.map(item => item.get('.rounded-md[aria-hidden="true"]').classes().join(' '))).toEqual([expect.stringContaining('text-warning'), expect.stringContaining('text-carbs')])
    expect(items[0]!.find('.lucide-flame').exists()).toBe(true)
    expect(button(wrapper, 'Tout marquer comme lu').exists()).toBe(true)
    wrapper.unmount()
  })

  it('reads one when it is opened, then goes where it points to', async () => {
    const unread = notification()
    fake.on('GET /api/notifications', () => [unread])
    fake.on('POST /api/notifications/{id}/read', () => ({ ...unread, readAt: '2026-09-30T19:00:00+00:00' }))
    const wrapper = await open(NotificationsPage)

    await wrapper.get('li button').trigger('click')
    await flushPromises()
    expect(fake.calls).toContainEqual({ route: 'POST /api/notifications/{id}/read', body: undefined, params: { path: { id: unread.id } } })
    expect(navigate).toHaveBeenCalledWith('/nutrition')
    expect(wrapper.get('li').text()).not.toContain('Non lue')
    wrapper.unmount()
  })

  it('reads the bell again after reading one', async () => {
    const unread = notification()
    useState('notifications-unread').value = 1
    fake.on('GET /api/notifications', () => [unread])
    fake.on('POST /api/notifications/{id}/read', () => ({ ...unread, readAt: '2026-09-30T19:00:00+00:00' }))
    const wrapper = await open(NotificationsPage)

    await wrapper.get('li button').trigger('click')
    await flushPromises()
    expect(useState('notifications-unread').value).toBe(0)
    wrapper.unmount()
  })

  it('reads one without a link where it is: the focus stays on it, and it says so', async () => {
    const unread = notification({ link: null })
    fake.on('GET /api/notifications', () => [unread])
    fake.on('POST /api/notifications/{id}/read', () => ({ ...unread, readAt: '2026-09-30T19:00:00+00:00' }))
    const wrapper = await open(NotificationsPage)

    await wrapper.get('li button').trigger('click')
    await flushPromises()
    expect(navigate).not.toHaveBeenCalled()
    expect(wrapper.find('li button').exists()).toBe(false)
    expect(document.activeElement).toBe(wrapper.get('li').element)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Notification marquée comme lue.')
    wrapper.unmount()
  })

  it('says when one cannot be read', async () => {
    fake.on('GET /api/notifications', () => [notification()])
    fake.on('POST /api/notifications/{id}/read', () => {
      throw apiError(503, null)
    })
    const wrapper = await open(NotificationsPage)

    await wrapper.get('li button').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(navigate).not.toHaveBeenCalled()
    expect(wrapper.get('li').text()).toContain('Non lue')
    wrapper.unmount()
  })

  it('offers to read them all when unread ones lie beyond the page shown, and reads the list again when new ones come', async () => {
    fake.on('GET /api/notifications', () => [notification({ readAt: '2026-09-30T19:00:00+00:00' })])
    useState('notifications-unread').value = 3
    const wrapper = await open(NotificationsPage)
    expect(button(wrapper, 'Tout marquer comme lu').exists()).toBe(true)

    // A check created one while the page is open: the list is read again.
    const loads = () => fake.calls.filter(call => call.route === 'GET /api/notifications').length
    const before = loads()
    fake.on('GET /api/notifications', () => [notification({ title: 'Nouveau record' }), notification({ readAt: '2026-09-30T19:00:00+00:00' })])
    useState('notifications-unread').value = 4
    await flushPromises()
    expect(loads()).toBe(before + 1)
    expect(wrapper.text()).toContain('Nouveau record')
    wrapper.unmount()
  })

  it('does not read one again, and a read one without a link is plain text', async () => {
    fake.on('GET /api/notifications', () => [notification({ readAt: '2026-09-30T19:00:00+00:00' }), notification({ readAt: '2026-09-30T19:00:00+00:00', link: null })])
    const wrapper = await open(NotificationsPage)

    expect(wrapper.findAll('li button')).toHaveLength(1)
    await wrapper.get('li button').trigger('click')
    await flushPromises()
    expect(fake.calls.some(call => call.route === 'POST /api/notifications/{id}/read')).toBe(false)
    expect(navigate).toHaveBeenCalledWith('/nutrition')
    wrapper.unmount()
  })

  it('marks all as read, says so and keeps the focus on the list', async () => {
    fake.on('GET /api/notifications', () => [notification(), notification()])
    fake.on('POST /api/notifications/read-all', () => undefined)
    const wrapper = await open(NotificationsPage)

    await button(wrapper, 'Tout marquer comme lu').trigger('click')
    await flushPromises()
    expect(wrapper.text()).not.toContain('Non lue')
    expect(wrapper.findAll('button').some(b => b.text() === 'Tout marquer comme lu')).toBe(false)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Toutes les notifications sont lues.')
    expect(document.activeElement).toBe(wrapper.get('ul').element)
    wrapper.unmount()
  })

  it('shows more with the cursor of the last one and moves the focus to the first added', async () => {
    const first = Array.from({ length: 20 }, () => notification({ readAt: '2026-09-30T19:00:00+00:00' }))
    const older = notification({ title: 'Rapport de la semaine prêt' })
    fake.on('GET /api/notifications', () => first)
    const wrapper = await open(NotificationsPage)

    fake.on('GET /api/notifications', () => [older])
    await button(wrapper, 'Voir plus').trigger('click')
    await flushPromises()
    const last = first.at(-1)!
    expect(fake.calls.at(-1)).toMatchObject({ route: 'GET /api/notifications', params: { query: { limit: 20, before: last.createdAt, beforeId: last.id } } })
    expect(wrapper.findAll('li')).toHaveLength(21)
    expect(document.activeElement?.textContent).toContain('Rapport de la semaine prêt')
    expect(wrapper.get('[data-testid="status"]').text()).toBe('1\u00A0notification de plus.')
    expect(wrapper.findAll('button').some(b => b.text() === 'Voir plus')).toBe(false)
    wrapper.unmount()
  })

  it('says there are no more when the next page is empty, the focus on the list', async () => {
    fake.on('GET /api/notifications', () => Array.from({ length: 20 }, () => notification({ readAt: '2026-09-30T19:00:00+00:00' })))
    const wrapper = await open(NotificationsPage)

    fake.on('GET /api/notifications', () => [])
    await button(wrapper, 'Voir plus').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Plus d’autres notifications.')
    expect(document.activeElement).toBe(wrapper.get('ul').element)
    wrapper.unmount()
  })

  it('has an empty state, and an error one can retry', async () => {
    fake.on('GET /api/notifications', () => [])
    const empty = await open(NotificationsPage)
    expect(empty.text()).toContain('Aucune notification')
    empty.unmount()

    clearNuxtData()
    fake.on('GET /api/notifications', () => {
      throw apiError(500, null)
    })
    const failed = await open(NotificationsPage)
    fake.on('GET /api/notifications', () => [notification()])
    await button(failed, 'Réessayer').trigger('click')
    await flushPromises()
    expect(failed.findAll('li')).toHaveLength(1)
    failed.unmount()
  })
})

describe('the notification settings', () => {
  it('saves the categories and the quiet hours', async () => {
    fake.on('GET /api/notifications/settings', () => notificationSettings())
    fake.on('PUT /api/notifications/settings', body => body)
    const wrapper = await open(SettingsPage)

    await field(wrapper, 'Records').setValue(false)
    await field(wrapper, 'À partir de').setValue('22:30')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.at(-1)).toMatchObject({
      route: 'PUT /api/notifications/settings',
      body: { categories: { ...notificationSettings().categories, record: false }, quietFrom: '22:30', quietTo: '08:00' },
    })
    expect(wrapper.text()).toContain('Réglages des notifications enregistrés.')
    wrapper.unmount()
  })

  it('sends no ends for no quiet hours, and offers the night again when turned back on', async () => {
    fake.on('GET /api/notifications/settings', () => notificationSettings({ quietFrom: null, quietTo: null }))
    fake.on('PUT /api/notifications/settings', body => body)
    const wrapper = await open(SettingsPage)

    const quiet = field(wrapper, 'Activer les horaires silencieux')
    expect((quiet.element as HTMLInputElement).checked).toBe(false)
    expect(wrapper.findAll('input[type="time"]')).toHaveLength(0)
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.at(-1)).toMatchObject({ body: { quietFrom: null, quietTo: null } })

    await quiet.setValue(true)
    expect((field(wrapper, 'À partir de').element as HTMLInputElement).value).toBe('23:00')
    expect((field(wrapper, 'Jusqu’à').element as HTMLInputElement).value).toBe('08:00')
    wrapper.unmount()
  })

  it('shows what the server refuses', async () => {
    fake.on('GET /api/notifications/settings', () => notificationSettings())
    fake.on('PUT /api/notifications/settings', () => {
      throw apiError(422, 'notification.invalid_settings', [{ propertyPath: 'quietFrom', message: 'Une heure HH:MM.' }])
    })
    const wrapper = await open(SettingsPage)

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(field(wrapper, 'À partir de').attributes('aria-invalid')).toBe('true')
    expect(wrapper.text()).toContain('Une heure HH:MM.')
    wrapper.unmount()
  })
})
