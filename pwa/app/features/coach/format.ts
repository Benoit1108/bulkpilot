import { formatKcal } from '~/features/nutrition/format'
import { formatKg, formatSignedKg } from '~/features/progression/format'
import { formatDayMonth, formatFullDay, localDay } from '~/services/calendar/days'
import { formatNumber } from '~/services/text/numbers'
import { plural } from '~/services/text/plural'
import type { AdherencePart, Decision, Recommendation, WeeklyReport } from './coach'

const NBSP = '\u00A0'

/**
 * The thresholds of the adherence, as the server applies them (the API describes them on
 * `AdherenceOutput`): only their wording lives here.
 */
const KCAL_TOLERANCE_PERCENT = 10
const PROTEIN_SHARE_PERCENT = 90

/** « 2 350 → 2 500 kcal par jour »: what accepting changes (or changed). */
export function proposalSummary(proposal: NonNullable<Recommendation['proposal']>): string {
  return `${formatNumber(proposal.fromKcal, 0)} → ${formatKcal(proposal.toKcal)} par jour`
}

/** « Protéines et lipides inchangés, glucides ajustés (environ +38 g). »: the whole of the change. */
export function proposalMacros(proposal: NonNullable<Recommendation['proposal']>): string {
  const grams = proposal.carbohydrateChangeGrams
  return `Protéines et lipides inchangés, glucides ajustés (environ ${grams > 0 ? '+' : grams < 0 ? '\u2212' : ''}${formatNumber(Math.abs(grams), 0)}${NBSP}g).`
}

/** One part of the adherence, explained by what was done of what was expected (spec §58). */
export function adherencePartText(part: AdherencePart): string {
  const days = `${part.done} ${plural(part.done, 'jour', 'jours')} sur ${part.expected}`
  switch (part.part) {
    case 'calories':
      return `Calories${NBSP}: ${days} à ±${KCAL_TOLERANCE_PERCENT}${NBSP}% de l’objectif`
    case 'protein':
      return `Protéines${NBSP}: ${days} à ${PROTEIN_SHARE_PERCENT}${NBSP}% de l’objectif ou plus`
    case 'training':
      return `Entraînement${NBSP}: ${part.done} ${plural(part.done, 'séance', 'séances')} sur ${part.expected} ${plural(part.expected, 'prévue', 'prévues')}`
    case 'weigh_ins':
      return `Pesées${NBSP}: ${part.done} sur ${part.expected} ${plural(part.expected, 'attendue', 'attendues')}`
  }
}

/** « Semaine du 21 septembre » (with the year when it is not this one). */
export function weekTitle(weekStart: string, today: string): string {
  return `Semaine du ${formatDayMonth(weekStart, today)}`
}

/** « 47,8 → 48,2 kg (+0,4 kg) », or what is known of it. */
export function reportWeight(report: WeeklyReport): string {
  if (report.endKg === null) return 'Pas de pesée cette semaine'
  if (report.startKg === null || report.changeKg === null) return formatKg(report.endKg)
  return `${formatNumber(report.startKg)} → ${formatKg(report.endKg)} (${formatSignedKg(report.changeKg)})`
}

/** « 2 310 kcal/j · 98 g de protéines/j (6 jours saisis) ». */
export function reportNutrition(report: WeeklyReport): string {
  if (report.averageKcal === null) return 'Rien de saisi cette semaine'
  const protein = report.averageProtein === null ? '' : ` · ${formatNumber(report.averageProtein, 0)}${NBSP}g de protéines/j`
  return `${formatKcal(report.averageKcal)}/j${protein} (${report.loggedDays} ${plural(report.loggedDays, 'jour saisi', 'jours saisis')})`
}

/** « 86 sur 100 », or a dash for a week with nothing recorded. */
export function reportAdherence(report: WeeklyReport): string {
  return report.adherence.score === null ? '—' : `${report.adherence.score} sur 100`
}

/** « 3/3 séances · 4 records ». */
export function reportTraining(report: WeeklyReport): string {
  const sessions = report.plannedSessions === null
    ? `${report.sessions} ${plural(report.sessions, 'séance', 'séances')}`
    : `${report.sessions}/${report.plannedSessions} ${plural(report.plannedSessions, 'séance', 'séances')}`
  const records = report.records === 0 ? 'aucun record' : `${report.records} ${plural(report.records, 'record', 'records')}`
  return `${sessions} · ${records}`
}

/**
 * What the user is told once their answer is recorded, from what the server answered (the day a
 * postponed one comes back).
 */
export function decisionNotice(decided: Recommendation, decision: Decision): string {
  const proposal = decided.proposal
  if (decision === 'postpone') {
    return decided.postponedUntil ? `Mise de côté jusqu’au ${formatFullDay(localDay(new Date(decided.postponedUntil)))}.` : 'Mise de côté.'
  }
  if (decision === 'reject') return proposal ? 'Proposition refusée, ton objectif ne change pas.' : 'Recommandation ignorée.'
  return proposal ? `Objectif passé à ${formatKcal(proposal.toKcal)} à partir de demain.` : 'C’est noté.'
}
