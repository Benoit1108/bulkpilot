import type { components } from '~/types/api'

export type Coach = components['schemas']['Coach']
/** As the Coach shows it; the history lists the same fields. */
export type Recommendation = Coach['recommendations'][number]
export type WeeklyReport = NonNullable<Coach['report']>
export type Adherence = NonNullable<Coach['adherence']>
export type AdherencePart = Adherence['parts'][number]
export type CoachSettings = components['schemas']['CoachSettings']
type Level = Recommendation['level']
export type Decision = 'accept' | 'reject' | 'postpone'

/** How much a recommendation matters (spec §68): the emoji and the word, never the colour alone. */
export const LEVELS: Readonly<Record<Level, { emoji: string, label: string, border: string }>> = {
  priority: { emoji: '🔴', label: 'Prioritaire', border: 'border-l-danger' },
  watch: { emoji: '🟠', label: 'À surveiller', border: 'border-l-accent' },
  info: { emoji: '🟢', label: 'Information', border: 'border-l-success' },
}

/**
 * Where a recommendation stands (spec §70), in the words of the button that led there: a proposal
 * is accepted or refused, a piece of advice is seen or ignored.
 */
export function statusLabel(recommendation: Pick<Recommendation, 'status' | 'proposal'>): string {
  const proposal = recommendation.proposal !== null
  switch (recommendation.status) {
    case 'pending': return 'En attente'
    case 'accepted': return proposal ? 'Acceptée' : 'Vue'
    case 'rejected': return proposal ? 'Refusée' : 'Ignorée'
    case 'postponed': return 'Reportée'
    case 'expired': return 'Expirée'
  }
}

/** Where to act on a piece of advice (spec §130: actionable); the other kinds have nowhere to go. */
export const CALLS_TO_ACTION: Readonly<Partial<Record<Recommendation['kind'], { label: string, to: string }>>> = {
  'calories.no_target': { label: 'Fixer mon objectif', to: '/nutrition/objectifs' },
  'calories.no_pace': { label: 'Régler le rythme', to: '/coach/reglages' },
  'journal.incomplete': { label: 'Ajouter un repas', to: '/nutrition' },
  'weigh_ins.few': { label: 'Ajouter une pesée', to: '/progression' },
}

/** How the Coach speaks (spec §131): the wording only, never the rules. */
export const PERSONALITIES: ReadonlyArray<{ value: CoachSettings['personality'], label: string, description: string }> = [
  { value: 'direct', label: 'Directe', description: 'Des consignes courtes, sans détour.' },
  { value: 'encouraging', label: 'Encourageante', description: 'Le même conseil, avec un mot pour la motivation.' },
  { value: 'neutral', label: 'Neutre', description: 'Les faits et la proposition, sans ton particulier.' },
]

/** The weight against the pace aimed at, in the weekly report. */
export const VERDICTS: Readonly<Record<WeeklyReport['verdict'], string>> = {
  on_track: 'Progression conforme',
  below: 'Plus lent que visé',
  above: 'Plus rapide que visé',
  unknown: 'Pas assez de données',
}

/**
 * useAsyncData keys shared by several pages, so that a write can invalidate what it changed.
 */
export const COACH_KEYS = {
  coach: (today: string) => `coach-${today}`,
  history: 'coach-history',
  reports: 'coach-reports',
  settings: 'coach-settings',
} as const

/** The Coach of a day (what a new tone or pace changes from the next analysis on). */
export function isCoachDay(key: string): boolean {
  return /^coach-\d{4}-\d{2}-\d{2}$/.test(key)
}
