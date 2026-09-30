import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import type { AccountState } from '~/features/identity/account'
import { useNotificationsBadge } from '~/features/notifications/badge'
import { createNotificationSync } from '~/features/notifications/sync'

/**
 * Once signed in, the device tells the server its time zone, then the checks run (on start and
 * whenever the app comes back to the foreground, at most every 5 minutes) and the bell is
 * refreshed. It is the only one to listen to the foreground: the bell only reads the count.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const account = useState<AccountState>(ACCOUNT_STATE_KEY)
  const badge = useNotificationsBadge(nuxtApp.$api)
  const sync = createNotificationSync(nuxtApp.$api, badge.refresh)

  async function start(): Promise<void> {
    const current = account.value
    if (!current) return
    // The time zone first: the checks judge "today" and "20:00" with it.
    await sync.syncTimeZone(current)
    await sync.check()
  }

  watch(() => account.value?.id, (id) => {
    if (id) void start()
    else {
      sync.reset()
      badge.reset()
    }
  }, { immediate: true })

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') void start()
  })
})
