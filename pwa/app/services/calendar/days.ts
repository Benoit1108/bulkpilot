/**
 * Calendar days as the API expects them: the user's *local* day, YYYY-MM-DD.
 * (`toISOString()` would give the UTC day, wrong in the evening east of Greenwich.)
 */
const DAY = /^\d{4}-\d{2}-\d{2}$/

export function localDay(date: Date = new Date()): string {
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

export function isDay(value: unknown): value is string {
  if (typeof value !== 'string' || !DAY.test(value)) return false
  return localDay(parseDay(value)) === value
}

export function parseDay(day: string): Date {
  const [year, month, date] = day.split('-').map(Number) as [number, number, number]
  return new Date(year, month - 1, date)
}

export function shiftDay(day: string, days: number): string {
  const date = parseDay(day)
  date.setDate(date.getDate() + days)
  return localDay(date)
}

const longDay = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const longDayWithYear = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

/** « Aujourd’hui », « Hier », « Demain », otherwise « lundi 28 septembre » (year when not this one). */
export function dayLabel(day: string, today: string): string {
  if (day === today) return 'Aujourd’hui'
  if (day === shiftDay(today, -1)) return 'Hier'
  if (day === shiftDay(today, 1)) return 'Demain'
  const format = day.slice(0, 4) === today.slice(0, 4) ? longDay : longDayWithYear
  return format.format(parseDay(day))
}

const shortDay = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' })
const fullDay = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })

/** « 12 sept. », under a chart. */
export function formatShortDay(day: string): string {
  return shortDay.format(parseDay(day))
}

/** « 12 septembre 2026 ». */
export function formatFullDay(day: string): string {
  return fullDay.format(parseDay(day))
}

const dayMonth = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long' })

/** « 21 septembre », with the year when it is not the one of `today` (« 21 septembre 2025 »). */
export function formatDayMonth(day: string, today: string): string {
  return (day.slice(0, 4) === today.slice(0, 4) ? dayMonth : fullDay).format(parseDay(day))
}
