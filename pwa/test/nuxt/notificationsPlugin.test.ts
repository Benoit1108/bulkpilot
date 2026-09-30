import { flushPromises } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import { useNotificationsBadge } from '~/features/notifications/badge'
import plugin from '~/plugins/notifications.client'
import { alex, createFakeApi } from '../support/fakeApi'

function zone(timeZone: string): void {
  vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue({ timeZone } as Intl.ResolvedDateTimeFormatOptions)
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('the notifications plugin', () => {
  it('tells the time zone, then runs the checks and reads the bell; again in the foreground; forgets all on sign-out', async () => {
    const fake = createFakeApi()
    fake.on('PUT /api/me/time-zone', () => undefined)
    fake.on('POST /api/notifications/check', () => undefined)
    fake.on('GET /api/notifications/unread', () => ({ count: 2 }))
    const nuxtApp = useNuxtApp()
    const account = useState(ACCOUNT_STATE_KEY)
    account.value = null
    zone('America/Montreal')
    // The plugin as the app starts it, with a client of its own.
    const app = new Proxy(nuxtApp, { get: (target, key) => (key === '$api' ? fake.client : Reflect.get(target, key)) })
    await nuxtApp.runWithContext(() => (plugin as unknown as (app: unknown) => unknown)(app))

    account.value = { ...alex, timeZone: 'Europe/Paris' }
    await flushPromises()
    expect(fake.calls.map(call => call.route)).toEqual(['PUT /api/me/time-zone', 'POST /api/notifications/check', 'GET /api/notifications/unread'])
    expect(useState('notifications-unread').value).toBe(2)

    // Back in the foreground at once: the zone is the server's, the checks ran less than 5 minutes ago.
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(fake.calls).toHaveLength(3)

    // Signed out: nothing of the account is shown.
    account.value = null
    await flushPromises()
    expect(useState('notifications-unread').value).toBe(0)
  })
  it('keeps the latest count when an older answer arrives last', async () => {
    const fake = createFakeApi()
    let answerFirst: (value: unknown) => void = () => {}
    fake.on('GET /api/notifications/unread', () => new Promise((resolve) => {
      answerFirst = resolve
    }))
    const badge = useNotificationsBadge(fake.client)
    const first = badge.refresh()
    fake.on('GET /api/notifications/unread', () => ({ count: 5 }))
    await badge.refresh()
    answerFirst({ count: 1 })
    await first
    expect(badge.count.value).toBe(5)
  })
})
