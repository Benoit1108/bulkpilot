import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import { flush, startWorkoutOutbox } from '~/features/training/outbox'
import type { AccountState } from '~/features/identity/account'
import { claimDevice, releaseDevice } from '~/services/offline/owner'

/**
 * Offline data follows the signed-in account: it is claimed (or wiped, for another account)
 * once the account is known, and the queue of writes starts on whatever page the app opens.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const account = useState<AccountState>(ACCOUNT_STATE_KEY)
  watch(account, async (current) => {
    if (!current) {
      releaseDevice()
      return
    }
    await claimDevice(current.id)
    startWorkoutOutbox(nuxtApp.$api)
    await flush()
  }, { immediate: true })
})
