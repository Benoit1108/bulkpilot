import { formatShortDay } from '~/services/calendar/days'
import { formatNumber } from '~/services/text/numbers'
import { PERIODS, type GoalEstimate, type Period, type WeightSummary } from './progression'

const tenth = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
const hundredth = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 })
const NBSP = '\u00A0'
const sign = (value: number) => (value > 0 ? '+' : value < 0 ? '−' : '')

/** « 62,4 kg »: always the tenth, as the scale shows it. */
export function formatKg(kg: number): string {
  return `${tenth.format(kg)}${NBSP}kg`
}

/** « +0,4 kg », « −1,2 kg », « 0,0 kg » (true minus sign). */
export function formatSignedKg(kg: number): string {
  // Halves away from zero, as the API rounds (Math.round would send −1,25 to −1,2).
  const rounded = Math.sign(kg) * Math.round(Math.abs(kg) * 10) / 10
  return `${sign(rounded)}${formatKg(Math.abs(rounded))}`
}

/** « 5 730 kg » lifted: to the kilogram. */
export function formatTonnage(kg: number): string {
  return `${formatNumber(kg, 0)}${NBSP}kg`
}

/** « 35,2 cm ». */
export function formatCm(cm: number): string {
  return `${tenth.format(cm)}${NBSP}cm`
}

/** « +1,2 cm », « −0,5 cm ». */
export function formatSignedCm(cm: number): string {
  const rounded = Math.sign(cm) * Math.round(Math.abs(cm) * 10) / 10
  return `${sign(rounded)}${formatCm(Math.abs(rounded))}`
}

/** « +0,42 kg/semaine ». */
export function formatSpeed(kgPerWeek: number): string {
  return `${sign(kgPerWeek)}${hundredth.format(Math.abs(kgPerWeek))}${NBSP}kg/semaine`
}

const DIRECTIONS: Record<NonNullable<WeightSummary['direction']>, string> = {
  gaining: 'en hausse',
  losing: 'en baisse',
  stable: 'stable',
}

/** « en hausse (+0,42 kg/semaine) », or why the speed is not known. */
export function formatTrend(summary: Pick<WeightSummary, 'kgPerWeek' | 'direction'>): string {
  if (summary.kgPerWeek === null || !summary.direction) return 'pas encore assez de pesées sur 4 semaines'
  return `${DIRECTIONS[summary.direction]} (${formatSpeed(summary.kgPerWeek)})`
}

/**
 * What the time left means (spec §53): an estimate at the current pace, never a promise.
 */
export function formatEstimate(estimate: GoalEstimate): string {
  switch (estimate.status) {
    case 'estimated':
      return `environ ${estimate.weeks}${NBSP}semaine${(estimate.weeks ?? 0) > 1 ? 's' : ''} au rythme actuel (estimation statistique)`
    case 'reached':
      return 'atteint'
    case 'not_enough_weigh_ins':
      return 'pas encore d’estimation\u00A0: il faut au moins 4 pesées sur une semaine ou plus'
    case 'not_moving':
      return 'pas d’estimation\u00A0: le poids est stable en ce moment'
    case 'moving_away':
      return 'pas d’estimation\u00A0: le poids va dans l’autre sens en ce moment'
  }
}

/** « sur les 3 derniers mois », « sur la dernière année », « depuis le début »: a sentence's span. */
export function periodLong(period: Period): string {
  return PERIODS.find(option => option.value === period)?.during ?? ''
}

/** The same, starting a sentence: « Sur la dernière année, … ». */
export function periodLongStart(period: Period): string {
  const phrase = periodLong(period)
  return phrase.charAt(0).toUpperCase() + phrase.slice(1)
}

/** « sem. du 12 oct. », and « (en cours) » for the week under way (`thisWeek`, its Monday). */
export function weekLabel(week: string, thisWeek: string): string {
  return `sem. du ${formatShortDay(week)}${week === thisWeek ? ' (en cours)' : ''}`
}

/** The weeks already over: the few days of the week under way would pull a weekly line. */
export function endedWeeks<T extends { week: string }>(weeks: readonly T[], thisWeek: string): T[] {
  return weeks.filter(week => week.week !== thisWeek)
}

/** Said under a weekly chart that leaves the week under way out. */
export const WEEK_UNDER_WAY = 'La semaine en cours apparaîtra une fois terminée.'
