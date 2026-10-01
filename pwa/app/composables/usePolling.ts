/**
 * Reads something again now and then while it is under way (an export being made): more and
 * more slowly (from `every` up to `max`), not while the page is hidden, and never after the
 * component went — even when a read was still on its way then. A failed read (it throws) is
 * tried again later, like any other.
 */
export function usePolling(active: () => boolean, read: () => Promise<unknown>, options: { every?: number, max?: number } = {}) {
  const every = options.every ?? 3000
  const max = options.max ?? 30_000
  let delay = every
  let timer: ReturnType<typeof setTimeout> | undefined
  let alive = true
  const hidden = () => document.visibilityState === 'hidden'

  /** Looks again later if it is still under way. */
  function schedule(): void {
    clearTimeout(timer)
    if (!alive || hidden() || !active()) return
    timer = setTimeout(tick, delay)
  }

  async function tick(): Promise<void> {
    try {
      await read()
    }
    catch {
      // Tried again below, a little later.
    }
    delay = Math.min(Math.round(delay * 1.5), max)
    schedule()
  }

  /** Something new started: looked at soon again, then more slowly. */
  function restart(): void {
    delay = every
    schedule()
  }

  function onVisibility(): void {
    if (!hidden()) restart()
  }
  onMounted(() => document.addEventListener('visibilitychange', onVisibility))
  onBeforeUnmount(() => {
    alive = false
    clearTimeout(timer)
    document.removeEventListener('visibilitychange', onVisibility)
  })

  return { schedule, restart }
}
