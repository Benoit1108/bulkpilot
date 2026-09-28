import { redirectFor } from '~/features/identity/access'

export default defineNuxtRouteMiddleware(async (to) => {
  const { account, load } = useAccount()
  if (account.value === undefined) {
    try {
      await load()
    }
    catch {
      // Server unreachable: let the page render (the connectivity banner explains);
      // personal data will not load anyway.
      return
    }
  }

  const redirect = redirectFor({ path: to.path, fullPath: to.fullPath, public: to.meta.public, guestOnly: to.meta.guestOnly }, account.value)
  if (redirect) return navigateTo(redirect)
})
