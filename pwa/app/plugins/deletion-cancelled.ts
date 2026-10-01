/**
 * A Google sign-in that cancelled the account's planned deletion says so in its redirect
 * (`?suppression_annulee=1`), whatever page it lands on: the notice is set and the query cleaned.
 * A password sign-in says it in its answer (useAccount).
 */
export default defineNuxtPlugin(() => {
  addRouteMiddleware('deletion-cancelled', (to) => {
    if (to.query.suppression_annulee !== '1') return
    useState(DELETION_CANCELLED_KEY, () => false).value = true
    const { suppression_annulee: _, ...query } = to.query
    return navigateTo({ path: to.path, query, hash: to.hash }, { replace: true })
  }, { global: true })
})
