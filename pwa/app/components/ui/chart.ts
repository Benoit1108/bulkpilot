/**
 * The geometry of a chart (ADR-0026): pure functions, tested on their own. Days are YYYY-MM-DD,
 * placed by their distance in days (a gap without weigh-ins shows as a gap).
 */
import { parseDay, shiftDay } from '~/services/calendar/days'

export interface ChartPoint { x: string, y: number }
export interface ChartSeries {
  name: string
  points: ChartPoint[]
  variant: 'line' | 'dashed' | 'dots'
  tone?: 'accent' | 'muted' | 'ink'
}
export interface ChartMark { y: number, label: string }

/** How a series is drawn: its line (solid, dashed, dots) tells it apart, not only its colour. */
export const STROKES = { accent: 'stroke-accent', muted: 'stroke-muted', ink: 'stroke-ink' } as const
export const FILLS = { accent: 'fill-accent', muted: 'fill-muted', ink: 'fill-ink' } as const
export const DASHES = { line: undefined, dashed: '6 4', dots: undefined } as const

/** Rough width of a label in the chart's small text (12px): enough to keep labels apart. */
export function textWidth(text: string): number {
  return text.length * 7
}

const DAY_MS = 86_400_000

export function dayNumber(day: string): number {
  return Math.round(parseDay(day).getTime() / DAY_MS)
}

/**
 * About `count` graduations on "round" values (1, 2, 2.5 or 5 times a power of ten) covering
 * [min, max]: 58, 60, 62… rather than 58.3, 60.1, 61.9.
 */
export function niceTicks(min: number, max: number, count = 5): number[] {
  if (min === max) {
    const pad = Math.max(1, Math.abs(min) * 0.02)
    return niceTicks(min - pad, max + pad, count)
  }
  const raw = (max - min) / Math.max(1, count - 1)
  const power = 10 ** Math.floor(Math.log10(raw))
  const step = ([1, 2, 2.5, 5, 10].find(factor => factor * power >= raw) ?? 10) * power
  const first = Math.floor(min / step) * step
  const ticks: number[] = []
  for (let value = first; value <= max + step / 2; value += step) ticks.push(Number(value.toFixed(10)))
  if (ticks[ticks.length - 1]! < max) ticks.push(Number((ticks[ticks.length - 1]! + step).toFixed(10)))
  return ticks
}

/** Maps a value of [from, to] onto [start, end] (a single value lands in the middle). */
export function scale(from: number, to: number, start: number, end: number): (value: number) => number {
  if (from === to) return () => (start + end) / 2
  return value => start + ((value - from) / (to - from)) * (end - start)
}

/** An SVG path through the points, in order. */
export function linePath(points: ReadonlyArray<{ x: number, y: number }>): string {
  return points.map((point, i) => `${i === 0 ? 'M' : 'L'}${point.x.toFixed(1)},${point.y.toFixed(1)}`).join(' ')
}

/**
 * Days to label under the chart, from `first` to `last`: at most `count`, evenly spread in time
 * (not by data point), both ends included.
 */
export function dayTicks(first: string, last: string, count = 4): string[] {
  const span = dayNumber(last) - dayNumber(first)
  if (span <= 0) return [first]
  const steps = Math.max(1, Math.min(count - 1, span))
  return [...new Set(Array.from({ length: steps + 1 }, (_, i) => shiftDay(first, Math.round((i * span) / steps))))]
}

/**
 * How many labels fit on an axis `length` wide, each about `label` wide: the first and last are
 * aligned on the ends, the others centred, so they need one and a half label of room each.
 */
export function tickCount(length: number, label: number, max = 6): number {
  return Math.max(2, Math.min(max, Math.floor(length / (label * 1.5)) + 1))
}

/** The index of the value nearest to `value` in a sorted list (a binary search). */
export function nearest(values: readonly number[], value: number): number {
  let low = 0
  let high = values.length - 1
  while (high - low > 1) {
    const middle = (low + high) >> 1
    if (values[middle]! < value) low = middle
    else high = middle
  }
  return Math.abs(values[high]! - value) < Math.abs(values[low]! - value) ? high : low
}
