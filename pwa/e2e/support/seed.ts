import type { Page } from '@playwright/test'
import { expect, JSON_HEADERS } from './fixtures'

/** The device's calendar day, `days` ago. */
export function dayAgo(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() - days)
  return new Intl.DateTimeFormat('sv-SE').format(date)
}

async function send(page: Page, method: 'post' | 'put', url: string, data: unknown): Promise<Record<string, unknown>> {
  const response = await page.request[method](url, { data, headers: JSON_HEADERS })
  expect(response.ok(), `${method.toUpperCase()} ${url}: ${await response.text()}`).toBe(true)
  return response.status() === 204 ? {} : await response.json() as Record<string, unknown>
}

export async function exerciseId(page: Page, query: string): Promise<string> {
  const response = await page.request.get(`/api/exercises?q=${encodeURIComponent(query)}`, { headers: JSON_HEADERS })
  const [first] = await response.json() as Array<{ id: string }>
  return first!.id
}

/** A one-day program, followed (the first one created is the active one). */
export async function program(page: Page): Promise<string> {
  const planned = (id: string, override: Record<string, unknown> = {}) => ({ exerciseId: id, sets: 2, repMin: 8, repMax: 12, targetRir: 2, restSeconds: 60, ...override })
  const created = await send(page, 'post', '/api/programs', {
    name: 'Full Body',
    days: [{ name: 'Full A', blocks: [
      { exercises: [planned(await exerciseId(page, 'developpe couche barre'))] },
      { exercises: [planned(await exerciseId(page, 'elevations laterales halteres'), { sets: 1, restSeconds: 0 }), planned(await exerciseId(page, 'gainage planche'), { sets: 1, repMin: 30, repMax: 60 })] },
    ] }],
  })
  return created.id as string
}

/** A finished workout with one set. */
export async function workout(page: Page, exercise: string, startedAt: Date, loadKg: number): Promise<string> {
  const id = crypto.randomUUID()
  const at = (minutes: number) => new Date(startedAt.getTime() + minutes * 60_000).toISOString()
  await send(page, 'put', `/api/workouts/${id}`, { startedAt: at(0) })
  await send(page, 'put', `/api/workouts/${id}/exercises/0`, { exerciseId: exercise })
  await send(page, 'put', `/api/workouts/${id}/sets/${crypto.randomUUID()}`, { exerciseIndex: 0, kind: 'normal', loadKg, reps: 8, doneAt: at(10) })
  await send(page, 'post', `/api/workouts/${id}/finish`, { finishedAt: at(45) })
  return id
}

export interface Populated {
  foodId: string
  recipeId: string
  exerciseId: string
  programId: string
  workoutId: string
}

/**
 * An account that has used every part of the app (pages then show their filled state): targets,
 * a journal, an own food and a recipe, weigh-ins and a weight goal, measurements, a program and a
 * finished workout. Everything through the API; the forms have their own tests.
 */
export async function populate(page: Page): Promise<Populated> {
  await send(page, 'post', '/api/nutrition/targets', { effectiveFrom: dayAgo(0), energyKcal: 2500, proteinGrams: 150, carbohydrateGrams: 300, fatGrams: 75 })
  const food = await send(page, 'post', '/api/foods', {
    name: 'Flocons d’avoine complets bio du petit producteur', state: 'not_applicable', energyKcal: 370, protein: 13, carbohydrate: 60, fat: 7,
    portions: [{ label: 'Bol', grams: 80 }],
  })
  const foodId = food.id as string
  const recipe = await send(page, 'post', '/api/recipes', { name: 'Porridge aux fruits rouges et beurre de cacahuète', servings: 2, ingredients: [{ foodId, grams: 160 }] })
  for (const slot of ['breakfast', 'lunch', 'dinner']) {
    await send(page, 'post', '/api/nutrition/entries', { foodId, day: dayAgo(0), slot, grams: 120 })
  }
  for (const [ago, kg] of [[28, 47.6], [21, 48.0], [14, 48.6], [7, 49.1], [1, 49.4]] as const) {
    await send(page, 'put', `/api/progression/weigh-ins/${dayAgo(ago)}`, { weightKg: kg })
  }
  await send(page, 'put', '/api/progression/weight-goal', { targetKg: 55, milestonesKg: [52] })
  await send(page, 'put', `/api/progression/measurements/${dayAgo(1)}`, { centimetres: { waist: 72.5, left_arm: 31 } })
  const programId = await program(page)
  const bench = await exerciseId(page, 'developpe couche barre')
  await workout(page, bench, new Date(Date.now() - 3 * 86_400_000), 55)
  const workoutId = await workout(page, bench, new Date(Date.now() - 86_400_000), 60)
  return { foodId, recipeId: recipe.id as string, exerciseId: bench, programId, workoutId }
}
