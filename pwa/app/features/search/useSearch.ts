import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { SEARCH_MIN_LENGTH } from '~/features/nutrition/rules'
import type { Food } from '~/features/nutrition/nutrition'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { exerciseResults, foodResults, GROUPS, programResults, recipeResults, searchPages, shortcutResults, workoutResults, type SearchResult } from './sources'

/** The wait after the last key before the foods are asked for (the rest is filtered on the device). */
export const FOOD_SEARCH_DELAY_MS = 250

export interface SearchResultGroup {
  value: string
  label: string
  /** Each with its place among all the results (the one the arrows move through). */
  results: Array<{ result: SearchResult, index: number }>
}

/**
 * Everything the palette finds for a query, grouped in a fixed order. The user's lists (recipes,
 * exercises, programs, sessions) are read under the keys their pages share, again at each opening
 * (a write elsewhere may have dropped or changed them), and filtered here; the foods are searched
 * by the API (CIQUAL is far too big to hold).
 */
export function useSearch(query: Ref<string>) {
  const api = useApi()
  const route = useRoute()
  const lists = {
    recipes: useAsyncData(NUTRITION_KEYS.recipes, () => api.get('/api/recipes')),
    exercises: useAsyncData(TRAINING_KEYS.exercises, () => api.get('/api/exercises')),
    programs: useAsyncData(TRAINING_KEYS.programs, () => api.get('/api/programs')),
    workouts: useAsyncData(TRAINING_KEYS.history, () => api.get('/api/workouts', { query: { limit: 50 } })),
  }
  async function refreshLists(): Promise<void> {
    await Promise.allSettled(Object.values(lists).map(list => list.refresh()))
  }

  const trimmed = computed(() => query.value.trim())
  // Kept until the next answer: the group does not blink at every key.
  const foods = ref<Food[]>([])
  const searchingFoods = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  // Only the answer to the latest query counts: an older one arriving last is dropped.
  let latest = 0
  watch(trimmed, (value) => {
    clearTimeout(timer)
    const asked = ++latest
    if (value.length < SEARCH_MIN_LENGTH) {
      foods.value = []
      searchingFoods.value = false
      return
    }
    searchingFoods.value = true
    timer = setTimeout(async () => {
      try {
        const found = await api.get('/api/foods', { query: { q: value, limit: 5 } })
        if (asked === latest) foods.value = found
      }
      catch {
        // Offline: the other groups still answer.
      }
      finally {
        if (asked === latest) searchingFoods.value = false
      }
    }, FOOD_SEARCH_DELAY_MS)
  })
  onBeforeUnmount(() => clearTimeout(timer))

  const results = computed<SearchResult[]>(() => {
    const value = trimmed.value
    if (!value) return shortcutResults(route.path)
    const found = [
      ...searchPages(value),
      ...foodResults(foods.value),
      ...recipeResults(lists.recipes.data.value ?? [], value),
      ...exerciseResults(lists.exercises.data.value ?? [], value),
      ...programResults(lists.programs.data.value ?? [], value),
      ...workoutResults(lists.workouts.data.value ?? [], value),
    ]
    return GROUPS.flatMap(group => found.filter(result => result.group === group.value))
  })

  /** The results by group, in one pass (they are already in the groups' order). */
  const groups = computed<SearchResultGroup[]>(() => {
    const byGroup: SearchResultGroup[] = []
    results.value.forEach((result, index) => {
      const last = byGroup.at(-1)
      if (last?.value === result.group) last.results.push({ result, index })
      else byGroup.push({ value: result.group, label: GROUPS.find(group => group.value === result.group)?.label ?? '', results: [{ result, index }] })
    })
    return byGroup
  })

  return { results, groups, searchingFoods: readonly(searchingFoods), refreshLists }
}
