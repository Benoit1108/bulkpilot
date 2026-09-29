type Sentinel = { release: () => Promise<void> }
type WakeLockNavigator = Navigator & { wakeLock?: { request: (type: 'screen') => Promise<Sentinel> } }

/**
 * Keeps the screen on while the component is mounted, where the browser allows it. Browsers
 * release the lock whenever the page is hidden, so it is asked for again on return.
 */
export function useWakeLock(): { keepScreenOn: () => void } {
  let lock: Sentinel | null = null
  let unmounted = false

  async function request(): Promise<void> {
    if (document.visibilityState !== 'visible' || unmounted) return
    try {
      const granted = await (navigator as WakeLockNavigator).wakeLock?.request('screen') ?? null
      if (unmounted) void granted?.release()
      else lock = granted
    }
    catch {
      lock = null
    }
  }
  const onVisibility = () => void request()

  onBeforeUnmount(() => {
    unmounted = true
    document.removeEventListener('visibilitychange', onVisibility)
    void lock?.release().catch(() => {})
  })

  return {
    keepScreenOn() {
      if (unmounted) return
      document.addEventListener('visibilitychange', onVisibility)
      void request()
    },
  }
}
