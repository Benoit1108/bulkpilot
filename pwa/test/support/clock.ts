import { afterEach, beforeEach, vi } from 'vitest'

/**
 * Freezes the date (not the timers) on Monday 28 September 2026 at noon, local time: "today",
 * the meal suggested for the time of day and relative labels no longer depend on when tests run.
 */
export const TODAY = '2026-09-28'

export function freezeToday(hour = 12): void {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date(2026, 8, 28, hour, 0))
  })
  afterEach(() => {
    vi.useRealTimers()
  })
}
