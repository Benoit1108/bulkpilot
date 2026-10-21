import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { PROGRESSION_KEYS, type Period } from './progression'

/**
 * What the progression pages set side by side: each context computes its own figures, the pages
 * only show them. Under the keys the sections use, so a page and its section share what they read;
 * each is asked for in one place of a page (two callers of one key would each fetch it).
 */
const periodQuery = (period: Ref<Period>, today: string) => () => ({ query: { period: period.value, today } })

export function useWeightOf(period: Ref<Period>, today: string) {
  const api = useApi()
  return useAsyncData(() => PROGRESSION_KEYS.weight(period.value, today), () => api.get('/api/progression/weight', periodQuery(period, today)()), { watch: [period] })
}

export function useIntakeOf(period: Ref<Period>, today: string) {
  const api = useApi()
  return useAsyncData(() => NUTRITION_KEYS.history(period.value, today), () => api.get('/api/nutrition/history', periodQuery(period, today)()), { watch: [period] })
}

export function useTrainingOf(period: Ref<Period>, today: string) {
  const api = useApi()
  return useAsyncData(() => TRAINING_KEYS.analytics(period.value, today), () => api.get('/api/training/analytics', periodQuery(period, today)()), { watch: [period] })
}

export function useMeasurementsOf(period: Ref<Period>, today: string) {
  const api = useApi()
  return useAsyncData(() => PROGRESSION_KEYS.measurements(period.value, today), () => api.get('/api/progression/measurements', periodQuery(period, today)()), { watch: [period] })
}

/** The week under way (sets by muscle) and the last full one (what it lifted), whatever the period. */
export function useTrainingWeek(today: string) {
  const api = useApi()
  return useAsyncData(TRAINING_KEYS.week(today), () => api.get('/api/training/week', { query: { today } }))
}
