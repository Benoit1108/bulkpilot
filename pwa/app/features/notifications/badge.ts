import type { ApiClient } from '~/services/api/client'

/**
 * The number of unread notifications, shared by the header bell and the notification pages. A
 * failure keeps the last count: the badge is a hint, never an error to show.
 */
export const UNREAD_STATE_KEY = 'notifications-unread'

/** Only the latest answer counts: an older one arriving last would bring back a stale count. */
let asked = 0

/** `api`: the notifications plugin passes the app's client (it runs outside any component). */
export function useNotificationsBadge(api: ApiClient = useApi()) {
  const count = useState<number>(UNREAD_STATE_KEY, () => 0)

  async function refresh(): Promise<void> {
    const mine = ++asked
    try {
      const unread = (await api.get('/api/notifications/unread')).count
      if (mine === asked) count.value = unread
    }
    catch {
      // Offline or signed out: the badge stays as it was.
    }
  }

  /** Signed out: nothing is shown of the account that was. */
  function reset(): void {
    asked++
    count.value = 0
  }

  return { count: readonly(count), refresh, reset }
}
