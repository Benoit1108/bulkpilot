import { describe, expect, it } from 'vitest'
import { dayNumber, dayTicks, linePath, nearest, niceTicks, scale, tickCount } from '~/components/ui/chart'

describe('chart geometry', () => {
  it.each([
    [58.3, 61.9, 5, [58, 59, 60, 61, 62]],
    [0, 2400, 5, [0, 1000, 2000, 3000]],
    [60, 60, 5, [58, 59, 60, 61, 62]],
    [0.12, 0.38, 3, [0, 0.2, 0.4]],
  ])('graduates %s–%s on round values', (min, max, count, ticks) => {
    expect(niceTicks(min, max, count)).toEqual(ticks)
  })

  it('covers the whole range', () => {
    const ticks = niceTicks(47.2, 63.8)
    expect(ticks[0]).toBeLessThanOrEqual(47.2)
    expect(ticks.at(-1)).toBeGreaterThanOrEqual(63.8)
  })

  it('maps values, and a lone one in the middle', () => {
    expect(scale(0, 10, 100, 0)(2.5)).toBe(75)
    expect(scale(5, 5, 0, 100)(5)).toBe(50)
  })

  it('places days by their distance, whatever the clock change', () => {
    expect(dayNumber('2026-10-26') - dayNumber('2026-10-24')).toBe(2)
    expect(dayNumber('2026-03-30') - dayNumber('2026-03-28')).toBe(2)
  })

  it('draws a path and labels a few days', () => {
    expect(linePath([{ x: 0, y: 10 }, { x: 5.25, y: 2 }])).toBe('M0.0,10.0 L5.3,2.0')
  })

  it('spreads the day labels over time, not over the points', () => {
    expect(dayTicks('2026-09-01', '2026-09-07', 3)).toEqual(['2026-09-01', '2026-09-04', '2026-09-07'])
    expect(dayTicks('2026-09-01', '2026-09-02', 4)).toEqual(['2026-09-01', '2026-09-02'])
    expect(dayTicks('2026-09-01', '2026-09-01', 4)).toEqual(['2026-09-01'])
    expect(dayTicks('2026-01-01', '2026-12-31', 5)).toEqual(['2026-01-01', '2026-04-02', '2026-07-02', '2026-10-01', '2026-12-31'])
  })

  it('fits as many labels as the width allows', () => {
    expect(tickCount(560, 60)).toBe(6)
    expect(tickCount(300, 60)).toBe(4)
    expect(tickCount(100, 60)).toBe(2)
  })

  it('finds the nearest point', () => {
    expect(nearest([0, 10, 20], 14)).toBe(1)
    expect(nearest([0, 10, 20], 16)).toBe(2)
    expect(nearest([0, 10, 20], -5)).toBe(0)
    expect(nearest([0, 10, 20], 99)).toBe(2)
    expect(nearest([7], 99)).toBe(0)
  })
})
