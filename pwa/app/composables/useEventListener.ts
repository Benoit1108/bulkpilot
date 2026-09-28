/** Adds a window listener for the lifetime of the calling component. */
export function useEventListener<K extends keyof WindowEventMap>(type: K, listener: (event: WindowEventMap[K]) => void): void {
  onMounted(() => window.addEventListener(type, listener))
  onBeforeUnmount(() => window.removeEventListener(type, listener))
}
