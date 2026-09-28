/**
 * Limits mirrored from the Nutrition domain for input attributes and hints only: the API enforces them.
 */
export const GRAMS = { min: 0.1, max: 5000 } as const // JournalEntry::MAX_GRAMS
export const ENERGY_PER_100G = { min: 0, max: 950 } as const // NutritionFacts::MAX_ENERGY_KCAL
export const TARGET_KCAL = { min: 1000, max: 7000 } as const // MacroTargets::MIN/MAX_ENERGY_KCAL
export const WEIGHT_KG = { min: 25, max: 350 } as const // EnergyInputs::MIN/MAX_WEIGHT_KG
export const SESSIONS_PER_WEEK = { min: 0, max: 14 } as const
export const WEEKLY_CHANGE_KG = { min: 0.1, max: 1 } as const
export const MAX_PORTIONS = 10 // Food::MAX_PORTIONS
export const SEARCH_MIN_LENGTH = 2 // SearchFoodsHandler::MIN_QUERY_LENGTH
/** Below this, the day's target is nearly reached: the Meal Builder has nothing to propose (MealBuilder::MIN_GOAL_KCAL). */
export const MEAL_BUILDER_MIN_KCAL = 100
/** Allowed gap between the calories and what the macros add up to (MacroTargets::TOLERANCE). */
export const TARGET_TOLERANCE = 0.05

/** Atwater factors, used for the "your macros add up to…" hint (the API checks the same). */
export function energyOfMacros(protein: number, carbohydrate: number, fat: number): number {
  return 4 * protein + 4 * carbohydrate + 9 * fat
}
