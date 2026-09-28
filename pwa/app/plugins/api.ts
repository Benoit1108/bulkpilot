import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import type { AccountState } from '~/features/identity/account'
import { createApiClient } from '~/services/api/client'

/**
 * One API client for the whole app. When a signed-in device loses its session (expired, revoked
 * from another device, password reset), personal data is dropped and the user goes to sign-in.
 * The expected 401 of a visitor who was never signed in triggers nothing.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const api = createApiClient({
    onUnauthenticated: () => {
      const account = useState<AccountState>(ACCOUNT_STATE_KEY)
      if (!account.value) return

      account.value = null
      clearNuxtData()
      const route = nuxtApp.$router.currentRoute.value
      if (!route.meta.public) {
        void nuxtApp.runWithContext(() => navigateTo({ path: '/connexion', query: { redirect: route.fullPath } }))
      }
    },
  })

  return { provide: { api } }
})
