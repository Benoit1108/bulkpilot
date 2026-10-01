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

/** « mercredi 30 septembre », the day at the top of the home page. */
export function formatLongDay(day: string): string {
  return longDay.format(parseDay(day))
}

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
const time = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' })

/** « 20:05 », a moment's local time. */
export function formatTime(moment: Date): string {
  return time.format(moment)
}

/** « 21 septembre », with the year when it is not the one of `today` (« 21 septembre 2025 »). */
export function formatDayMonth(day: string, today: string): string {
  return (day.slice(0, 4) === today.slice(0, 4) ? dayMonth : fullDay).format(parseDay(day))
}

/** « Aujourd’hui à 20:05 », « Hier à 08:30 », or the day (and its year when not this one) and the time. */
export function formatMoment(at: string, now = new Date()): string {
  const moment = momentLabel(at, now)
  return `${moment.charAt(0).toUpperCase()}${moment.slice(1)}`
}

/** The same within a sentence: « aujourd’hui à 09:58 », « lundi 21 septembre à 09:00 ». */
export function momentLabel(at: string, now = new Date()): string {
  const moment = new Date(at)
  const day = dayLabel(localDay(moment), localDay(now))
  return `${day.charAt(0).toLowerCase()}${day.slice(1)} à ${formatTime(moment)}`
}
