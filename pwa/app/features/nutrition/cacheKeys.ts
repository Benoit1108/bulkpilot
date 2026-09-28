/**
 * useAsyncData keys shared by several pages, so that a write can invalidate what it changed.
 */
export const NUTRITION_KEYS = {
  recentFoods: 'nutrition-recent-foods',
  customFoods: 'nutrition-custom-foods',
  recipes: 'nutrition-recipes',
  favoriteMeals: 'nutrition-favorite-meals',
  targets: 'nutrition-targets',
  mealSplit: 'nutrition-meal-split',
  day: (day: string) => `nutrition-day-${day}`,
  averages: (days: number, today: string) => `nutrition-averages-${days}-${today}`,
  food: (id: string) => `nutrition-food-${id}`,
  recipe: (id: string) => `nutrition-recipe-${id}`,
} as const

/** Everything computed from the journal: days and averages. */
export function isJournalDerived(key: string): boolean {
  return key.startsWith('nutrition-day-') || key.startsWith('nutrition-averages-')
}
