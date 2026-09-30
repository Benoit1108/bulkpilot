import type { Account } from '~/features/identity/account'
import { asApiError, type ApiClient } from '~/services/api/client'

/** The checks run at most this often from one device (the server also skips a recent one). */
export const CHECK_INTERVAL_MS = 5 * 60 * 1000

/** The device's IANA time zone (« Europe/Paris »), when the browser tells it. */
export function deviceTimeZone(): string | null {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || null
  }
  catch {
    return null
  }
}

/**
 * Keeps the server in step with the device: its time zone (the day and the hours the scheduled
 * notifications follow), and the checks that create notifications, run when the app opens or
 * comes back. Failures are quiet: none of it is something the user asked for.
 */
export function createNotificationSync(api: ApiClient, refreshBadge: () => Promise<void>, now: () => number = Date.now) {
  let lastCheck = Number.NEGATIVE_INFINITY
  /** What the server has since this device told it (the account read at start does not follow). */
  let sentZone: string | null = null
  /** A zone the server refused (unknown to it): not sent again at every opening. */
  let refusedZone: string | null = null

  async function syncTimeZone(account: Pick<Account, 'timeZone'>): Promise<boolean> {
    const zone = deviceTimeZone()
    if (!zone || zone === (sentZone ?? account.timeZone) || zone === refusedZone) return false
    try {
      await api.put('/api/me/time-zone', { timeZone: zone })
      sentZone = zone
      return true
    }
    catch (caught) {
      if (asApiError(caught).kind === 'validation') refusedZone = zone
      return false
    }
  }

  async function check(): Promise<void> {
    if (now() - lastCheck < CHECK_INTERVAL_MS) return
    lastCheck = now()
    try {
      await api.post('/api/notifications/check')
    }
    catch {
      // The next opening tries again.
    }
    await refreshBadge()
  }

  /** Signed out: the next account starts afresh. */
  function reset(): void {
    lastCheck = Number.NEGATIVE_INFINITY
    sentZone = null
    refusedZone = null
  }

  return { syncTimeZone, check, reset }
}
