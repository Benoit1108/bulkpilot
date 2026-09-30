import type { InjectionKey } from 'vue'
import { PROGRESSION_KEYS } from '~/features/progression/progression'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { asApiError, type ApiClient } from '~/services/api/client'
import type { WidgetKey } from './dashboard'

/**
 * What several widgets read (and « Pour commencer » too): one fetch per key, whoever asks first.
 * The keys are the pages' own, so the dashboard and the sections share their data.
 */

/** Nothing there is null, not an error (no active program, no weight goal). */
async function orNull<T>(read: () => Promise<T>): Promise<T | null> {
  try {
    return await read()
  }
  catch (caught) {
    if (asApiError(caught).kind === 'not_found') return null
    throw caught
  }
}

export const fetchNextWorkout = (api: ApiClient) => orNull(() => api.get('/api/training/next-workout'))

export function useNextWorkout() {
  const api = useApi()
  return useAsyncData(TRAINING_KEYS.nextWorkout, () => fetchNextWorkout(api))
}

export function useWeightGoal(today: () => string) {
  const api = useApi()
  return useAsyncData(PROGRESSION_KEYS.goal, () => orNull(() => api.get('/api/progression/weight-goal', { query: { today: today() } })))
}

/** The weight of the last 7 or 30 days, as the weight page reads it. */
export function useWeightSummary(period: () => '7d' | '30d', today: () => string) {
  const api = useApi()
  return useAsyncData(
    () => PROGRESSION_KEYS.weight(period(), today()),
    () => api.get('/api/progression/weight', { query: { period: period(), today: today() } }),
  )
}

/** The sessions and records of the last 7 or 30 days. */
export function useTrainingAnalytics(period: () => '7d' | '30d', today: () => string) {
  const api = useApi()
  return useAsyncData(
    () => TRAINING_KEYS.analytics(period(), today()),
    () => api.get('/api/training/analytics', { query: { period: period(), today: today() } }),
  )
}

/** The widgets shown now: one may leave to another what they would both say (the day's meals). */
export const SHOWN_WIDGETS: InjectionKey<Readonly<Ref<readonly WidgetKey[]>>> = Symbol('shown widgets')
