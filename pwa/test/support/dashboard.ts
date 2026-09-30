import type { DashboardLayout, WidgetKey } from '~/features/dashboard/dashboard'
import type { components } from '~/types/api'

type NextWorkout = components['schemas']['NextWorkout']
type TrainingAnalytics = components['schemas']['TrainingAnalytics']

export const DEFAULT_ORDER: WidgetKey[] = ['weight', 'nutrition', 'meals', 'workout', 'coach', 'goal', 'records', 'report']

export function layout(hidden: WidgetKey[] = [], order: WidgetKey[] = DEFAULT_ORDER): DashboardLayout {
  return { widgets: order.map(key => ({ key, visible: !hidden.includes(key) })) }
}

export const nextWorkout: NextWorkout = {
  programId: '0199a0b1-0000-7000-8000-0000000000a1',
  programName: 'PPL',
  versionNumber: 2,
  dayIndex: 1,
  dayName: 'Pull',
  exerciseCount: 5,
  lastDoneAt: '2026-09-24T17:30:00+00:00',
  inProgress: false,
}

export function analytics(sessions: number, records: number, period: TrainingAnalytics['period'] = '7d'): TrainingAnalytics {
  const totals = { sessions, workingSets: sessions * 15, reps: sessions * 120, tonnageKg: sessions * 5000, records }
  return { period, totals, weeks: [], sessionsPerWeek: sessions ? sessions / (period === '30d' ? 30 / 7 : 1) : null }
}
