import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { shiftDay, weekStart } from '~/services/calendar/days'
import type { components, operations } from '~/types/api'

type History = components['schemas']['NutritionHistory']
type Period = NonNullable<NonNullable<operations['api_nutritionhistory_get']['parameters']['query']>['period']>

/**
 * The shortest history that still covers `from`. A period counts today in (Period.php: « 7d » starts
 * six days back), and the API reads « today » as the device's day only: an older week is reached by
 * a longer period, not by moving « today ».
 */
export function periodReaching(from: string, today: string): Period {
  const back = Math.round((Date.parse(today) - Date.parse(from)) / 86_400_000)
  if (back < 7) return '7d'
  if (back < 30) return '30d'
  if (back < 91) return '3m'
  if (back < 182) return '6m'
  if (back < 365) return '1y'
  return 'all'
}

/**
 * The calories of each day of the week of `day`, as the history has them: today is left out (it is
 * under way), the caller knows it from today's journal. Refreshed with the journal's writes.
 */
export function useLoggedWeek(day: () => string, today: () => string) {
  const api = useApi()
  const period = computed(() => periodReaching(weekStart(day()), today()))
  const { data } = useAsyncData(
    () => NUTRITION_KEYS.history(period.value, today()),
    () => api.get('/api/nutrition/history', { query: { period: period.value, today: today() } }),
    { lazy: true },
  )
  const week = computed(() => Array.from({ length: 7 }, (_, i) => shiftDay(weekStart(day()), i)))
  const kcal = computed(() => {
    const byDay = new Map((data.value?.days ?? []).map(entry => [entry.day, entry.energyKcal]))
    return week.value.map(date => ({ day: date, energyKcal: byDay.get(date) ?? null }))
  })
  return { kcal, target: computed<History['target']>(() => data.value?.target ?? null) }
}
