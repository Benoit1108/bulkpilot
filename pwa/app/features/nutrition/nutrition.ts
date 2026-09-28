import type { components } from '~/types/api'

export type NutritionDay = components['schemas']['NutritionDay']
export type Meal = components['schemas']['Meal']
export type Entry = components['schemas']['Entry']
export type Nutrients = components['schemas']['Nutrients']
export type Food = components['schemas']['Food']
export type CustomFoodInput = components['schemas']['Food.CustomFoodInput']
export type NutritionTarget = components['schemas']['NutritionTarget']
export type DayTarget = NonNullable<NutritionDay['target']>
export type EnergyEstimate = components['schemas']['EnergyEstimate']
export type EstimateInput = components['schemas']['EstimateInput']
export type MealSplit = components['schemas']['MealSplit']
export type NutritionAverages = components['schemas']['NutritionAverages']
export type Recipe = components['schemas']['Recipe']
export type RecipeInput = components['schemas']['Recipe.RecipeInput']
export type FavoriteMeal = components['schemas']['FavoriteMeal']
export type BarcodeScan = components['schemas']['BarcodeScan']
export type ScannedProduct = NonNullable<BarcodeScan['product']>
export type MealBuilderResult = components['schemas']['MealBuilder']
export type Proposal = MealBuilderResult['proposals'][number]

/** What can be logged with a quantity: a food, or a dish (grams of the cooked recipe). */
export type Loggable = { kind: 'food', food: Food } | { kind: 'recipe', recipe: Recipe }
export type LogSource = { foodId: string } | { recipeId: string }

export type MealSlot = Meal['slot']
export type Goal = EstimateInput['goal']
export type Lifestyle = EstimateInput['lifestyle']
export type FoodState = Food['state']

/** Chronological, as the API returns the meals of a day. */
export const MEAL_SLOTS: ReadonlyArray<{ value: MealSlot, label: string, to: string }> = [
  { value: 'breakfast', label: 'Petit-déjeuner', to: 'au petit-déjeuner' },
  { value: 'lunch', label: 'Déjeuner', to: 'au déjeuner' },
  { value: 'snack', label: 'Collation', to: 'à la collation' },
  { value: 'gainer', label: 'Gainer', to: 'au gainer' },
  { value: 'dinner', label: 'Dîner', to: 'au dîner' },
]

export function slotLabel(slot: MealSlot): string {
  return MEAL_SLOTS.find(option => option.value === slot)?.label ?? slot
}

/**
 * The meal a proposal most likely goes to at this time of day (the user can pick another).
 */
export function slotAt(date: Date = new Date()): MealSlot {
  const hour = date.getHours()
  if (hour < 10) return 'breakfast'
  if (hour < 15) return 'lunch'
  if (hour < 17) return 'snack'
  if (hour < 19) return 'gainer'
  return 'dinner'
}

/** « au déjeuner », « à la collation »: for "Ajouter …". */
export function toSlot(slot: MealSlot): string {
  return MEAL_SLOTS.find(option => option.value === slot)?.to ?? slot
}

export const GOALS: ReadonlyArray<{ value: Goal, label: string }> = [
  { value: 'bulk', label: 'Prise de masse' },
  { value: 'maintain', label: 'Maintien' },
  { value: 'cut', label: 'Sèche' },
]

export const LIFESTYLES: ReadonlyArray<{ value: Lifestyle, label: string, hint: string }> = [
  { value: 'sedentary', label: 'Sédentaire', hint: 'Bureau, peu de marche' },
  { value: 'light', label: 'Peu actif', hint: 'Marche régulière' },
  { value: 'active', label: 'Actif', hint: 'Debout une bonne partie de la journée' },
  { value: 'very_active', label: 'Très actif', hint: 'Travail physique' },
]

export const FOOD_STATES: ReadonlyArray<{ value: FoodState, label: string }> = [
  { value: 'raw', label: 'Cru' },
  { value: 'cooked', label: 'Cuit' },
  { value: 'not_applicable', label: 'Sans objet' },
]

export const TARGET_SOURCES: Record<NutritionTarget['source'], string> = {
  estimate: 'Estimation',
  manual: 'Saisie manuelle',
  coach: 'Coach',
}

/** Lowercase, accent-free text for filtering lists on the device (the API does the same for search). */
export function searchKey(text: string): string {
  return text.toLowerCase().replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/ß/g, 'ss').normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/\s+/g, ' ').trim()
}

export function matches(name: string, query: string): boolean {
  const key = searchKey(name)
  return searchKey(query).split(' ').every(word => key.includes(word))
}
