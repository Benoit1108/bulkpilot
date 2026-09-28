/** Waiting service-worker update, as exposed by @vite-pwa/nuxt (absent in dev and tests). */
export function usePwaUpdate() {
  const { $pwa } = useNuxtApp()

  return {
    needRefresh: computed(() => $pwa?.needRefresh ?? false),
    applyUpdate: () => $pwa?.updateServiceWorker(true),
  }
}
