import type { ResponseOf } from '~/services/api/contract'

export type AppNotification = ResponseOf<'/api/notifications', 'get'>[number]
export type NotificationSettings = ResponseOf<'/api/notifications/settings', 'get'>
export type Category = keyof NotificationSettings['categories']

/** What each category is about (spec §75), as the settings describe it. */
export const CATEGORIES: ReadonlyArray<{ value: Category, label: string, description: string }> = [
  { value: 'low_calories', label: 'Calories très insuffisantes', description: 'Le soir, quand tu es loin de ton objectif du jour.' },
  { value: 'workout_not_started', label: 'Séance prévue non commencée', description: 'En fin de journée, quand la séance du jour est nécessaire pour tenir ta semaine.' },
  { value: 'missing_weigh_in', label: 'Pesée manquante', description: 'Le matin, quand tu te pèses d’habitude et pas encore aujourd’hui.' },
  { value: 'goal_reached', label: 'Objectif atteint', description: 'Quand tu franchis un jalon ou ton poids cible.' },
  { value: 'record', label: 'Records', description: 'Quand une séance bat un de tes records.' },
  { value: 'weekly_report', label: 'Rapport hebdomadaire', description: 'Quand le rapport de la semaine du Coach est prêt.' },
]

/** Proposed when quiet hours are turned back on (spec §76). */
export const DEFAULT_QUIET_HOURS = { from: '23:00', to: '08:00' } as const

/** No quiet hours: both ends null. */
export function hasQuietHours(settings: Pick<NotificationSettings, 'quietFrom' | 'quietTo'>): boolean {
  return settings.quietFrom !== null && settings.quietTo !== null
}

export const NOTIFICATION_KEYS = {
  list: 'notifications',
  settings: 'notifications-settings',
} as const
