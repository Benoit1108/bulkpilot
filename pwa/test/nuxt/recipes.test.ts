import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import FavoritesPage from '~/pages/nutrition/favoris.vue'
import NutritionPage from '~/pages/nutrition/index.vue'
import RecipePage from '~/pages/nutrition/recettes/[id].vue'
import RecipesPage from '~/pages/nutrition/recettes/index.vue'
import NewRecipePage from '~/pages/nutrition/recettes/nouvelle.vue'
import { freezeToday, TODAY } from '../support/clock'
import { button, buttonStartingWith, confirm, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { day, entry, favorite, recipe, rice, skyr } from '../support/nutrition'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))

mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)

const fake = createFakeApi()
api.current = fake
freezeToday()

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  fake.on('GET /api/foods/recent', () => [rice])
  fake.on('GET /api/recipes', () => [recipe])
  fake.on('GET /api/nutrition/favorite-meals', () => [favorite])
  fake.on('GET /api/nutrition/averages', () => ({ days: 7, loggedDays: 0, dailyAverage: null, currentTarget: null }))
  fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
})

async function openPicker(slot: string) {
  const wrapper = await mountSuspended(NutritionPage, { route: '/nutrition' })
  await flushPromises()
  await button(wrapper, `Ajouter un aliment ${slot}`).trigger('click')
  await flushPromises()
  return wrapper
}

describe('logging a recipe or a favourite meal', () => {
  it('lists favourites, then recent foods, then a few recipes', async () => {
    const wrapper = await openPicker('au déjeuner')

    const headings = wrapper.get('dialog[open]').findAll('h3').map(h => h.text())
    expect(headings).toEqual(['Repas favoris', 'Récents', 'Mes recettes'])
  })

  it('logs servings of a recipe', async () => {
    fake.on('POST /api/nutrition/entries', () => day(TODAY))
    const wrapper = await openPicker('au déjeuner')

    expect(wrapper.get('dialog[open]').text()).toContain('327 kcal / portion')
    await buttonStartingWith(wrapper, 'Riz au poulet', 'dialog[open]').trigger('click')
    expect((field(wrapper, 'Quantité').element as HTMLInputElement).value).toBe('275')
    expect(wrapper.get('dialog[open] .quantity__hint').text()).toBe('Une portion pèse 275 g.')
    await button(wrapper, '2 portions (550 g)').trigger('click')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/entries')?.body).toEqual({ recipeId: recipe.id, day: TODAY, slot: 'lunch', grams: 550 })
  })

  it('filters recipes by name as the user types', async () => {
    fake.on('GET /api/foods', () => [])
    const wrapper = await openPicker('au dîner')

    await field(wrapper, 'Rechercher un aliment ou une recette').setValue('poulet riz')
    expect(wrapper.get('dialog[open] [aria-label="Mes recettes"]').text()).toContain('Riz au poulet')
    expect(wrapper.find('dialog[open] [aria-label="Repas favoris"]').exists()).toBe(false)
    await field(wrapper, 'Rechercher un aliment ou une recette').setValue('pâtes')
    expect(wrapper.find('dialog[open] [aria-label="Mes recettes"]').exists()).toBe(false)
  })

  it('adds a whole favourite meal in one tap, and says so', async () => {
    fake.on('POST /api/nutrition/favorite-meals/{id}/log', () => day(TODAY, [entry('breakfast', 150, skyr)]))
    const wrapper = await openPicker('au petit-déjeuner')

    await buttonStartingWith(wrapper, 'Ajouter le repas favori Petit-déj habituel', 'dialog[open]').trigger('click')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/favorite-meals/{id}/log')).toMatchObject({ body: { day: TODAY, slot: 'breakfast' }, params: { path: { id: favorite.id } } })
    expect(wrapper.find('dialog[open]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="meal-breakfast"]').text()).toContain('Skyr nature')
    expect(wrapper.get('.ui-status').text()).toBe('« Petit-déj habituel » ajouté au petit-déjeuner (2 éléments).')
  })

  it('shows why a favourite could not be added', async () => {
    fake.on('POST /api/nutrition/favorite-meals/{id}/log', () => {
      throw apiError(404, 'nutrition.favorite_meal_not_found')
    })
    const wrapper = await openPicker('au petit-déjeuner')

    await buttonStartingWith(wrapper, 'Ajouter le repas favori Petit-déj habituel', 'dialog[open]').trigger('click')
    await flushPromises()

    expect(wrapper.get('dialog[open] [role="alert"]').text()).toBe('Ce repas favori n’existe plus.')
  })

  it('saves a logged meal as a favourite', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, [entry('snack', 150, skyr)]))
    fake.on('POST /api/nutrition/favorite-meals', () => favorite)
    const wrapper = await mountSuspended(NutritionPage, { route: '/nutrition' })
    await flushPromises()
    expect(wrapper.findAll('button').some(b => b.attributes('aria-label') === 'Enregistrer « Déjeuner » comme repas favori'), 'only meals with something in them').toBe(false)

    await button(wrapper, 'Enregistrer « Collation » comme repas favori').trigger('click')
    await flushPromises()
    expect((field(wrapper, 'Nom du repas').element as HTMLInputElement).value).toBe('Collation')
    await field(wrapper, 'Nom du repas').setValue('Goûter')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/favorite-meals')?.body).toEqual({ name: 'Goûter', day: TODAY, slot: 'snack' })
    expect(wrapper.get('.ui-status').text()).toBe('« Goûter » est dans tes repas favoris.')
  })

  it('explains a refused favourite', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, [entry('snack', 150, skyr)]))
    fake.on('POST /api/nutrition/favorite-meals', () => {
      throw apiError(422, 'nutrition.invalid_favorite_meal')
    })
    const wrapper = await mountSuspended(NutritionPage, { route: '/nutrition' })
    await flushPromises()
    await button(wrapper, 'Enregistrer « Collation » comme repas favori').trigger('click')
    await flushPromises()

    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('dialog[open] [role="alert"]').text()).toContain('Ce repas est vide')
  })
})

describe('recipes', () => {
  it('lists recipes with their value per serving, explains them, or offers a retry', async () => {
    const listed = await mountSuspended(RecipesPage, { route: '/nutrition/recettes' })
    await flushPromises()
    expect(listed.get(`a[href="/nutrition/recettes/${recipe.id}"]`).text()).toContain('4 portions · 327 kcal / portion')

    fake.on('GET /api/recipes', () => [])
    clearNuxtData()
    const empty = await mountSuspended(RecipesPage, { route: '/nutrition/recettes' })
    await flushPromises()
    expect(empty.text()).toContain('Aucune recette')

    fake.on('GET /api/recipes', () => {
      throw apiError(503, null)
    })
    clearNuxtData()
    const offline = await mountSuspended(RecipesPage, { route: '/nutrition/recettes' })
    await flushPromises()
    expect(offline.text()).toContain('Impossible de charger ces données.')
  })

  it('composes a recipe from weighed ingredients, the search never submitting it', async () => {
    fake.on('GET /api/foods', () => [rice])
    fake.on('POST /api/recipes', () => recipe)
    const wrapper = await mountSuspended(NewRecipePage, { route: '/nutrition/recettes/nouvelle' })
    await flushPromises()

    await field(wrapper, 'Nom de la recette').setValue('Riz au poulet')
    await field(wrapper, 'Poids une fois cuit').setValue('1100')
    await button(wrapper, 'Ajouter un ingrédient').trigger('click')
    await flushPromises()
    expect(wrapper.find('dialog[open] [aria-label="Mes recettes"]').exists(), 'no recipe inside a recipe').toBe(false)
    expect(wrapper.find('dialog[open]').element.closest('form'), 'the picker is not inside the recipe form').toBeNull()
    await buttonStartingWith(wrapper, 'Riz blanc', 'dialog[open]').trigger('click')
    await flushPromises()
    await field(wrapper, 'Riz blanc, cru').setValue('300,5')
    await button(wrapper, 'Ajouter un ingrédient').trigger('click')
    await flushPromises()
    await buttonStartingWith(wrapper, 'Riz blanc', 'dialog[open]').trigger('click')
    await flushPromises()
    await wrapper.findAll('button').filter(b => b.attributes('aria-label') === 'Retirer Riz blanc, cru').at(-1)!.trigger('click')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/recipes')?.body).toEqual({ name: 'Riz au poulet', servings: 4, cookedWeightGrams: 1100, ingredients: [{ foodId: rice.id, grams: 300.5 }] })
    expect(navigate).toHaveBeenCalledWith(`/nutrition/recettes/${recipe.id}`)
  })

  it('leaves blank values out, so that the refusal names them', async () => {
    fake.on('POST /api/recipes', () => {
      throw apiError(422, null, [{ propertyPath: 'servings', message: 'Cette valeur ne doit pas être nulle.' }])
    })
    const wrapper = await mountSuspended(NewRecipePage, { route: '/nutrition/recettes/nouvelle' })
    await field(wrapper, 'Nombre de portions').setValue('')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.at(-1)?.body).toEqual({ name: '', servings: undefined, cookedWeightGrams: null, ingredients: [] })
    expect(wrapper.text()).toContain('Cette valeur ne doit pas être nulle.')
  })

  it('shows the composition, edits and deletes a recipe', async () => {
    fake.on('GET /api/recipes/{id}', () => recipe)
    fake.on('PUT /api/recipes/{id}', body => ({ ...recipe, ...(body as object) }))
    fake.on('DELETE /api/recipes/{id}', () => undefined)
    const wrapper = await mountSuspended(RecipePage, { route: `/nutrition/recettes/${recipe.id}` })
    await flushPromises()

    const composition = wrapper.get('table').text()
    expect(composition).toContain('Par portion')
    expect(composition).toContain('327 kcal')
    expect(composition).toContain('plat cuit')
    expect(wrapper.text()).toContain('Skyr nature (aliment supprimé)')

    await field(wrapper, 'Nombre de portions').setValue('5')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'PUT /api/recipes/{id}')).toMatchObject({ body: { servings: 5, ingredients: [{ foodId: rice.id, grams: 300 }, { foodId: skyr.id, grams: 400 }] }, params: { path: { id: recipe.id } } })
    expect(wrapper.get('.ui-status').text()).toContain('Recette enregistrée.')

    await confirm(wrapper, 'Supprimer la recette')
    await flushPromises()
    expect(navigate).toHaveBeenCalledWith('/nutrition/recettes')
  })

  it('says when a recipe is gone, when it could not be loaded, or deleted', async () => {
    fake.on('GET /api/recipes/{id}', () => recipe)
    fake.on('DELETE /api/recipes/{id}', () => {
      throw apiError(404, 'nutrition.recipe_not_found')
    })
    const wrapper = await mountSuspended(RecipePage, { route: `/nutrition/recettes/${recipe.id}` })
    await flushPromises()
    await confirm(wrapper, 'Supprimer la recette')
    await flushPromises()
    expect(wrapper.text()).toContain('Cette recette n’existe plus.')
    expect(navigate).not.toHaveBeenCalled()

    clearNuxtData()
    fake.on('GET /api/recipes/{id}', () => {
      throw apiError(404, 'nutrition.recipe_not_found')
    })
    const missing = await mountSuspended(RecipePage, { route: `/nutrition/recettes/${recipe.id}` })
    await flushPromises()
    expect(missing.text()).toContain('Cette recette n’existe plus.')
    expect(missing.find('form').exists()).toBe(false)

    clearNuxtData()
    fake.on('GET /api/recipes/{id}', () => {
      throw apiError(503, null)
    })
    const offline = await mountSuspended(RecipePage, { route: `/nutrition/recettes/${recipe.id}` })
    await flushPromises()
    expect(offline.text()).not.toContain('n’existe plus')
    expect(offline.text()).toContain('Impossible de charger ces données.')
  })
})

describe('favourite meals page', () => {
  it('lists favourites with their items and deletes one after confirmation', async () => {
    let favorites = [favorite]
    fake.on('GET /api/nutrition/favorite-meals', () => favorites)
    fake.on('DELETE /api/nutrition/favorite-meals/{id}', () => {
      favorites = []
    })
    const wrapper = await mountSuspended(FavoritesPage, { route: '/nutrition/favoris' })
    await flushPromises()
    expect(wrapper.text()).toContain('Skyr nature · 150 g')
    expect(wrapper.text()).toContain('422 kcal')

    await confirm(wrapper, 'Supprimer')
    await flushPromises()

    expect(wrapper.text()).toContain('Aucun repas favori')
    expect(wrapper.get('.ui-status').text()).toBe('« Petit-déj habituel » supprimé des repas favoris.')
  })

  it('reports a failed deletion, or a list that could not be loaded', async () => {
    fake.on('DELETE /api/nutrition/favorite-meals/{id}', () => {
      throw apiError(404, 'nutrition.favorite_meal_not_found')
    })
    const wrapper = await mountSuspended(FavoritesPage, { route: '/nutrition/favoris' })
    await flushPromises()
    await confirm(wrapper, 'Supprimer')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toBe('Ce repas favori n’existe plus.')

    clearNuxtData()
    fake.on('GET /api/nutrition/favorite-meals', () => {
      throw apiError(503, null)
    })
    const offline = await mountSuspended(FavoritesPage, { route: '/nutrition/favoris' })
    await flushPromises()
    expect(offline.text()).toContain('Impossible de charger ces données.')
  })
})
