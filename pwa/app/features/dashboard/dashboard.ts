import type { components } from '~/types/api'

export type DashboardLayout = components['schemas']['DashboardLayout']
export type Widget = DashboardLayout['widgets'][number]
export type WidgetKey = Widget['key']

/** What the dashboard covers (spec §13): the day, or the last 7 or 30 days. */
export type DashboardView = 'day' | 'week' | 'month'

export const VIEWS: ReadonlyArray<{ value: DashboardView, label: string, long: string }> = [
  { value: 'day', label: 'Jour', long: 'aujourd’hui' },
  { value: 'week', label: 'Semaine', long: 'ces 7 derniers jours' },
  { value: 'month', label: 'Mois', long: 'ces 30 derniers jours' },
]

/** The number of days a view covers, and the period the analytics endpoints know it by. */
export const viewDays = (view: DashboardView): 7 | 30 => (view === 'month' ? 30 : 7)
export const viewPeriod = (view: DashboardView): '7d' | '30d' => (view === 'month' ? '30d' : '7d')
export const viewLong = (view: DashboardView): string => VIEWS.find(option => option.value === view)?.long ?? ''

/** The widgets' names, in the account's default order (the server gives the user's). */
export const WIDGET_LABELS: Readonly<Record<WidgetKey, string>> = {
  weight: 'Poids',
  nutrition: 'Nutrition',
  meals: 'Repas',
  workout: 'Entraînement',
  coach: 'Coach',
  goal: 'Objectif de poids',
  records: 'Records',
  report: 'Rapport de la semaine',
}

export const DASHBOARD_KEYS = {
  layout: 'dashboard-layout',
} as const

/**
 * A widget this version of the app knows. A newer server may know more: those are kept in the
 * layout (and sent back as they were) but not shown.
 */
export function isKnownWidget(widget: Widget): boolean {
  return Object.hasOwn(WIDGET_LABELS, widget.key)
}

/** The list with one widget moved by one place (unchanged at either end). */
export function moveWidget(widgets: readonly Widget[], index: number, by: -1 | 1): Widget[] {
  const target = index + by
  const moved = [...widgets]
  if (target < 0 || target >= moved.length) return moved
  const [widget] = moved.splice(index, 1)
  moved.splice(target, 0, widget!)
  return moved
}
