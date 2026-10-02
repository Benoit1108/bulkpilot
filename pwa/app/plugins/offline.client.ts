import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import { flush, startWorkoutOutbox } from '~/features/training/outbox'
import type { AccountState } from '~/features/identity/account'
import { redirectFor } from '~/features/identity/access'
import { claimDevice, releaseDevice } from '~/services/offline/owner'

/**
 * Offline data follows the signed-in account: it is claimed (or wiped, for another account)
 * once the account is known, and the queue of writes starts on whatever page the app opens.
 * Opened without a network, the account is not known yet: it is asked again when the network
 * comes back (or the app comes back to the foreground), so that what was recorded offline goes
 * out without a reload. Found signed out by then (the session expired meanwhile), a protected
 * page leaves for the sign-in page, as a navigation would.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const account = useState<AccountState>(ACCOUNT_STATE_KEY)
  const router = useRouter()
  // `online` and `visibilitychange` often come together (the phone unlocked): one request.
  let asking: Promise<void> | null = null
  async function ask(): Promise<void> {
    try {
      const known = await nuxtApp.runWithContext(() => useAccount().load())
      const route = router.currentRoute.value
      const redirect = known ? null : redirectFor({ path: route.path, fullPath: route.fullPath, public: route.meta.public, guestOnly: route.meta.guestOnly }, known)
      if (redirect) await nuxtApp.runWithContext(() => navigateTo(redirect))
    }
    catch {
      // Still unreachable: asked again at the next chance.
    }
  }
  function knowAccount(): Promise<void> {
    if (account.value !== undefined) return Promise.resolve()
    asking ??= ask().finally(() => {
      asking = null
    })
    return asking
  }
  window.addEventListener('online', () => void knowAccount())
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') void knowAccount()
  })

  watch(account, async (current) => {
    // Signed out (null), or not known yet (undefined, offline): nothing is sent meanwhile.
    if (!current) {
      releaseDevice()
      return
    }
    await claimDevice(current.id)
    startWorkoutOutbox(nuxtApp.$api)
    await flush()
  }, { immediate: true })
})
