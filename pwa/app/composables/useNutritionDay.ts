import { isJournalDerived, isShownBesideTheDay, NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import type { LogSource, MealSlot, NutritionDay } from '~/features/nutrition/nutrition'

export interface EntryChange {
  day: string
  slot: MealSlot
  grams: number
}

/**
 * One day of the food journal. Every write answers with the day it changed, so the view is
 * redrawn from the server's numbers (the PWA never adds nutrients up itself).
 *
 * `journal` is the day shown only once it *is* that day: when the key changes, Nuxt keeps the
 * previous day's data until the new one arrives, and editing it would write to the wrong day.
 * That previous day is `previous`: shown inert while the next one loads, so the page keeps its
 * height instead of collapsing to a loading line and growing back (layout shift).
 */
export function useNutritionDay(day: Ref<string>) {
  const api = useApi()
  const nuxtApp = useNuxtApp()
  const { data, status, error, refresh } = useAsyncData(
    () => NUTRITION_KEYS.day(day.value),
    () => api.get('/api/nutrition/days/{day}', { path: { day: day.value } }),
  )

  const journal = computed<NutritionDay | null>(() => (data.value?.day === day.value ? data.value : null))
  const previous = computed<NutritionDay | null>(() => (data.value && data.value.day !== day.value ? data.value : null))

  /**
   * Shows the day the server answered with (or reloads it: an entry moved away, a load in flight),
   * then drops what else the journal feeds (other days, meal builder) and reloads the averages
   * and the week shown next to it.
   */
  async function changed(result: NutritionDay | null): Promise<void> {
    if (result && result.day === day.value && status.value !== 'pending') data.value = result
    else await refresh()
    const current = NUTRITION_KEYS.day(day.value)
    clearNuxtData(key => isJournalDerived(key) && key !== current && !isShownBesideTheDay(key))
    await refreshNuxtData(Object.keys(nuxtApp.payload.data).filter(isShownBesideTheDay))
  }

  async function log(source: LogSource, slot: MealSlot, grams: number): Promise<void> {
    await changed(await api.post('/api/nutrition/entries', { ...source, day: day.value, slot, grams }))
  }

  /** Several foods at once, all or nothing (a Meal Builder proposal). */
  async function logMany(items: Array<{ foodId: string, grams: number }>, slot: MealSlot): Promise<void> {
    await changed(await api.post('/api/nutrition/entries/batch', { day: day.value, slot, items }))
  }

  /** Every item of a favourite meal, in one go. */
  async function logFavorite(favoriteId: string, slot: MealSlot): Promise<void> {
    await changed(await api.post('/api/nutrition/favorite-meals/{id}/log', { day: day.value, slot }, { path: { id: favoriteId } }))
  }

  async function correct(entryId: string, change: EntryChange): Promise<void> {
    await changed(await api.put('/api/nutrition/entries/{id}', change, { path: { id: entryId } }))
  }

  async function remove(entryId: string): Promise<void> {
    await api.delete('/api/nutrition/entries/{id}', { path: { id: entryId } })
    await changed(null)
  }

  return { journal, previous, status, error, refresh, log, logMany, logFavorite, correct, remove }
}
