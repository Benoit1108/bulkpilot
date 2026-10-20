import type { EnergyEstimate, Entry, FavoriteMeal, Food, Meal, MealBuilderResult, MealSlot, NutritionDay, NutritionTarget, Recipe } from '~/features/nutrition/nutrition'

export const rice: Food = {
  id: '0199a0b1-0000-7000-8000-00000000f001',
  source: 'ciqual',
  name: 'Riz blanc, cru',
  brand: null,
  category: 'Céréales',
  state: 'raw',
  per100g: { energyKcal: 352, protein: 7.4, carbohydrate: 78.6, fat: 0.7, fibre: 1.4, sugars: 0.3, saturatedFat: 0.2, salt: 0.01 },
  portions: [],
  editable: false,
  barcode: null,
  lastGrams: null,
}

export const skyr: Food = {
  ...rice,
  id: '0199a0b1-0000-7000-8000-00000000f002',
  source: 'custom',
  name: 'Skyr nature',
  brand: 'Siggi’s',
  state: 'not_applicable',
  per100g: { energyKcal: 63, protein: 11, carbohydrate: 4, fat: 0.2, fibre: null, sugars: 4, saturatedFat: null, salt: null },
  portions: [{ label: '1 pot', grams: 150 }],
  editable: true,
}

export const target: NutritionTarget = {
  id: '0199a0b1-0000-7000-8000-000000000071',
  effectiveFrom: '2026-09-01',
  energyKcal: 3000,
  proteinGrams: 150,
  carbohydrateGrams: 400,
  fatGrams: 88,
  source: 'estimate',
  estimate: null,
  setAt: '2026-09-01T08:00:00+00:00',
}

export const estimate: EnergyEstimate = {
  age: 30,
  sex: 'male',
  heightCm: 180,
  weightKg: 80,
  lifestyle: 'active',
  trainingSessionsPerWeek: 4,
  goal: 'bulk',
  weeklyWeightChangeKg: 0.25,
  basalMetabolicRate: 1780,
  activityLevel: 1.5,
  trainingAllowance: 0.2,
  totalExpenditure: 3026,
  dailyAdjustment: 275,
  energyKcal: 3300,
  proteinGrams: 144,
  carbohydrateGrams: 474,
  fatGrams: 92,
}

const zero = { energyKcal: 0, protein: 0, carbohydrate: 0, fat: 0, fibre: 0 }

export function entry(slot: MealSlot, grams = 100, food: Food = rice): Entry {
  const factor = grams / 100
  return {
    id: `0199a0b1-0000-7000-8000-0000000e${slot.length}${grams}`.slice(0, 36),
    slot,
    foodId: food.id,
    recipeId: null,
    name: food.name,
    state: food.state,
    grams,
    nutrients: {
      energyKcal: food.per100g.energyKcal * factor,
      protein: food.per100g.protein * factor,
      carbohydrate: food.per100g.carbohydrate * factor,
      fat: food.per100g.fat * factor,
      fibre: null,
    },
    per100g: { energyKcal: food.per100g.energyKcal, protein: food.per100g.protein, carbohydrate: food.per100g.carbohydrate, fat: food.per100g.fat, fibre: food.per100g.fibre },
    loggedAt: '2026-09-28T12:00:00+00:00',
  }
}

const SHARES: Record<MealSlot, number> = { breakfast: 25, lunch: 30, snack: 0, gainer: 15, dinner: 30 }

/** A day whose totals are the sum of its entries, like the API computes them. */
export function day(date: string, entries: Entry[] = [], withTarget: NutritionTarget | null = target): NutritionDay {
  const meals: Meal[] = (Object.keys(SHARES) as MealSlot[]).map((slot) => {
    const ofSlot = entries.filter(e => e.slot === slot)
    return {
      slot,
      share: SHARES[slot],
      targetEnergyKcal: withTarget ? Math.round(withTarget.energyKcal * SHARES[slot] / 100) : null,
      totals: sum(ofSlot),
      entries: ofSlot,
    }
  })
  return { day: date, totals: sum(entries), target: withTarget, meals }
}

function sum(entries: Entry[]) {
  return entries.reduce((total, e) => ({
    energyKcal: total.energyKcal + e.nutrients.energyKcal,
    protein: total.protein + e.nutrients.protein,
    carbohydrate: total.carbohydrate + e.nutrients.carbohydrate,
    fat: total.fat + e.nutrients.fat,
    fibre: total.fibre,
  }), { ...zero })
}

const nutrients = (energyKcal: number, protein: number, carbohydrate: number, fat: number) => ({ energyKcal, protein, carbohydrate, fat, fibre: null })

export const recipe: Recipe = {
  id: '0199a0b1-0000-7000-8000-0000000000ae',
  name: 'Riz au poulet',
  servings: 4,
  cookedWeightGrams: 1100,
  ingredients: [
    { foodId: rice.id, name: rice.name, state: 'raw', grams: 300, nutrients: nutrients(1056, 22.2, 235.8, 2.1), available: true },
    { foodId: skyr.id, name: skyr.name, state: 'not_applicable', grams: 400, nutrients: nutrients(252, 44, 16, 0.8), available: false },
  ],
  totalGrams: 1100,
  servingGrams: 275,
  total: nutrients(1308, 66.2, 251.8, 2.9),
  perServing: nutrients(327, 16.6, 63, 0.7),
  per100g: nutrients(118.9, 6, 22.9, 0.3),
}

export const favorite: FavoriteMeal = {
  id: '0199a0b1-0000-7000-8000-0000000000fa',
  name: 'Petit-déj habituel',
  items: [
    { name: skyr.name, grams: 150, nutrients: nutrients(94.5, 16.5, 6, 0.3), recipe: false },
    { name: recipe.name, grams: 275, nutrients: recipe.perServing, recipe: true },
  ],
  totals: nutrients(421.5, 33.1, 69, 1),
}

export const chicken: Food = {
  ...rice,
  id: '0199a0b1-0000-7000-8000-00000000f003',
  name: 'Poulet, filet, sauté/poêlé',
  state: 'cooked',
  per100g: { energyKcal: 141, protein: 30.1, carbohydrate: 0, fat: 2, fibre: null, sugars: null, saturatedFat: null, salt: null },
}

export const oil: Food = {
  ...rice,
  id: '0199a0b1-0000-7000-8000-00000000f004',
  name: 'Huile de tournesol',
  state: 'not_applicable',
  per100g: { energyKcal: 900, protein: 0, carbohydrate: 0, fat: 100, fibre: null, sugars: null, saturatedFat: null, salt: null },
}

export function builderResult(date: string, slot: MealSlot | null = null): MealBuilderResult {
  return {
    day: date,
    slot,
    remaining: nutrients(820, 42, 105, 25),
    proposals: [{
      items: [
        { foodId: chicken.id, name: chicken.name, role: 'protein', grams: 140, nutrients: nutrients(197, 42, 0, 2.8) },
        { foodId: rice.id, name: 'Riz blanc, cuit', role: 'carbohydrate', grams: 320, nutrients: nutrients(467, 9.3, 101, 1.6) },
        { foodId: oil.id, name: oil.name, role: 'fat', grams: 18, nutrients: nutrients(162, 0, 0, 18) },
      ],
      totals: nutrients(826, 51.3, 101, 22.4),
      gapKcal: 45,
    }],
  }
}
