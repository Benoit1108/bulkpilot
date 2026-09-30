/**
 * The global « + » (and the search) lead to a page with `?ajouter=<what>`: once the form is on the
 * page (it may wait for its data), it takes the focus, then the URL is left clean (going back
 * does not ask again).
 */
export function useAddRequest(what: string, form: Ref<{ focus: () => void } | null>): void {
  const route = useRoute()
  const asked = computed(() => route.query.ajouter === what)
  watch([asked, form], async ([now, target]) => {
    if (!now || !target) return
    await nextTick()
    target.focus()
    const { ajouter: _asked, ...rest } = route.query
    await navigateTo({ query: rest }, { replace: true })
  }, { immediate: true })
}
