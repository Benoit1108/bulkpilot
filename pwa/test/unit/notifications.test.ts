import { afterEach, describe, expect, it, vi } from 'vitest'
import { hasQuietHours, notificationTime } from '~/features/notifications/notifications'
import { CHECK_INTERVAL_MS, createNotificationSync } from '~/features/notifications/sync'
import { apiError, createFakeApi } from '../support/fakeApi'

function zone(timeZone: string): void {
  vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue({ timeZone } as Intl.ResolvedDateTimeFormatOptions)
}

afterEach(() => {
  vi.restoreAllMocks()
})

describe('notification sync', () => {
  it('sends the device time zone only when it differs from the account\'s, once', async () => {
    const fake = createFakeApi()
    fake.on('PUT /api/me/time-zone', () => undefined)
    const sync = createNotificationSync(fake.client, async () => {})
    zone('Europe/Paris')

    expect(await sync.syncTimeZone({ timeZone: 'Europe/Paris' })).toBe(false)
    expect(fake.calls).toHaveLength(0)

    zone('America/Montreal')
    expect(await sync.syncTimeZone({ timeZone: 'Europe/Paris' })).toBe(true)
    expect(fake.calls).toEqual([{ route: 'PUT /api/me/time-zone', body: { timeZone: 'America/Montreal' }, params: undefined }])
    // The account read at start still says Paris: Montréal is not sent again.
    expect(await sync.syncTimeZone({ timeZone: 'Europe/Paris' })).toBe(false)
    expect(fake.calls).toHaveLength(1)

    // Back home in the same session: the server has Montréal, so Paris is sent.
    zone('Europe/Paris')
    expect(await sync.syncTimeZone({ timeZone: 'Europe/Paris' })).toBe(true)
    expect(fake.calls.map(call => call.body)).toEqual([{ timeZone: 'America/Montreal' }, { timeZone: 'Europe/Paris' }])
  })

  it('stops sending a zone the server refused, and tries again after a failure of the network', async () => {
    const fake = createFakeApi()
    fake.on('PUT /api/me/time-zone', () => {
      throw apiError(422, 'identity.invalid_time_zone')
    })
    const sync = createNotificationSync(fake.client, async () => {})
    zone('Mars/Olympus')
    expect(await sync.syncTimeZone({ timeZone: 'Europe/Paris' })).toBe(false)
    expect(await sync.syncTimeZone({ timeZone: 'Europe/Paris' })).toBe(false)
    expect(fake.calls).toHaveLength(1)

    fake.on('PUT /api/me/time-zone', () => {
      throw apiError(503, null)
    })
    zone('Asia/Tokyo')
    await sync.syncTimeZone({ timeZone: 'Europe/Paris' })
    await sync.syncTimeZone({ timeZone: 'Europe/Paris' })
    expect(fake.calls).toHaveLength(3)
  })

  it('keeps quiet when the time zone cannot be sent', async () => {
    const fake = createFakeApi()
    zone('Asia/Tokyo')
    const sync = createNotificationSync(fake.client, async () => {})
    expect(await sync.syncTimeZone({ timeZone: 'Europe/Paris' })).toBe(false)
  })

  it('runs the checks at most every 5 minutes, then refreshes the bell', async () => {
    const fake = createFakeApi()
    fake.on('POST /api/notifications/check', () => undefined)
    const refresh = vi.fn(async () => {})
    let now = 1_000_000
    const sync = createNotificationSync(fake.client, refresh, () => now)

    await sync.check()
    await sync.check()
    now += CHECK_INTERVAL_MS - 1
    await sync.check()
    expect(fake.calls.filter(call => call.route === 'POST /api/notifications/check')).toHaveLength(1)
    expect(refresh).toHaveBeenCalledTimes(1)

    now += 1
    await sync.check()
    expect(fake.calls.filter(call => call.route === 'POST /api/notifications/check')).toHaveLength(2)

    // Another account signing in on the device is checked at once.
    sync.reset()
    await sync.check()
    expect(fake.calls.filter(call => call.route === 'POST /api/notifications/check')).toHaveLength(3)
  })

  it('still refreshes the bell when the checks fail', async () => {
    const fake = createFakeApi()
    const refresh = vi.fn(async () => {})
    await createNotificationSync(fake.client, refresh).check()
    expect(refresh).toHaveBeenCalledOnce()
  })
})

describe('notification wording', () => {
  it('says when it arrived', () => {
    const now = new Date(2026, 8, 30, 21, 0)
    expect(notificationTime(new Date(2026, 8, 30, 20, 5).toISOString(), now)).toBe('Aujourd’hui à 20:05')
    expect(notificationTime(new Date(2026, 8, 29, 8, 30).toISOString(), now)).toBe('Hier à 08:30')
    expect(notificationTime(new Date(2026, 8, 21, 9, 0).toISOString(), now)).toBe('Lundi 21 septembre à 09:00')
  })

  it('knows no quiet hours by their missing ends', () => {
    expect(hasQuietHours({ quietFrom: '23:00', quietTo: '08:00' })).toBe(true)
    expect(hasQuietHours({ quietFrom: null, quietTo: null })).toBe(false)
  })
})
