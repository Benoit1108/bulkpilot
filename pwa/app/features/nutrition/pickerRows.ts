import { formatGrams, formatKcal } from '~/features/nutrition/format'
import { macrosFor, type Food, type Loggable, type Macros, type Recipe } from '~/features/nutrition/nutrition'

/** The lists shown before anything is typed, one per tab. */
export type PickerTab = 'recent' | 'favorites' | 'recipes'

/**
 * What a line of the add panel shows, and what its quick « + » adds: `grams`, said as `amount`
 * (null: no quantity known, the line sets one).
 */
export interface PickerRow {
  key: string
  name: string
  detail: string
  macros: Macros | null
  item: Loggable
  grams: number | null
  amount: string
}

/**
 * A recent food says the quantity logged last and what it brings (only recent foods carry
 * `lastGrams`); a found one, its kcal per 100 g.
 */
export function foodRow(food: Food): PickerRow {
  const grams = food.lastGrams
  const amount = grams === null ? '' : formatGrams(grams)
  return {
    key: food.id,
    name: food.name,
    detail: grams === null ? [food.brand, `${formatKcal(food.per100g.energyKcal)} / 100\u00A0g`].filter(Boolean).join(' · ') : amount,
    macros: grams === null ? null : macrosFor(food.per100g, grams),
    item: { kind: 'food', food },
    grams,
    amount,
  }
}

/** A recipe is added by the serving. */
export function recipeRow(recipe: Recipe): PickerRow {
  return { key: recipe.id, name: recipe.name, detail: '1 portion', macros: recipe.perServing, item: { kind: 'recipe', recipe }, grams: recipe.servingGrams, amount: '1 portion' }
}
