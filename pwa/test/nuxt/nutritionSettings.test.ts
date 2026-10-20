import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises, type VueWrapper } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Drawer from '~/components/ui/Drawer.vue'
import FoodEditPage from '~/pages/nutrition/aliments/[id].vue'
import FoodsPage from '~/pages/nutrition/aliments/index.vue'
import NewFoodPage from '~/pages/nutrition/aliments/nouveau.vue'
import TargetsPage from '~/pages/nutrition/objectifs.vue'
import SplitPage from '~/pages/nutrition/repartition.vue'
import { freezeToday, TODAY } from '../support/clock'
import { confirm } from '../support/dom'
import { alex, apiError, createFakeApi } from '../support/fakeApi'
import { estimate, rice, skyr, target } from '../support/nutrition'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))

mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)

const fake = createFakeApi()
api.current = fake
freezeToday()

type Wrapper = VueWrapper

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  useState('account').value = { ...alex, sex: 'male', birthDate: '1996-01-15', heightCm: 180 }
})

function field(wrapper: Wrapper, label: string) {
  const labelElement = wrapper.findAll('label').find(l => l.text() === label)
  expect(labelElement, label).toBeDefined()
  return wrapper.get(`#${labelElement!.attributes('for')}`)
}

function button(wrapper: Wrapper, text: string) {
  const found = wrapper.findAll('button').find(b => b.text() === text || b.attributes('aria-label') === text)
  expect(found, text).toBeDefined()
  return found!
}

describe('targets page', () => {
  it('estimates from the profile, shows each step, and saves the accepted targets', async () => {
    fake.on('GET /api/nutrition/targets', () => [])
    fake.on('POST /api/nutrition/targets/estimate', () => estimate)
    fake.on('POST /api/nutrition/targets', () => target)
    const wrapper = await mountSuspended(TargetsPage, { route: '/nutrition/objectifs' })
    await flushPromises()
    expect(wrapper.text()).toContain('Aucun objectif enregistré')

    expect((field(wrapper, 'Taille').element as HTMLInputElement).value).toBe('180')
    await field(wrapper, 'Poids actuel').setValue('80')
    await field(wrapper, 'Mode de vie (hors sport)').setValue('active')
    await field(wrapper, 'Séances par semaine').setValue('4')
    await wrapper.findAll('form')[0]!.trigger('submit')
    await flushPromises()

    const request = { sex: 'male', birthDate: '1996-01-15', heightCm: 180, weightKg: 80, lifestyle: 'active', trainingSessionsPerWeek: 4, goal: 'bulk', weeklyWeightChangeKg: 0.25 }
    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/targets/estimate')?.body).toEqual(request)
    const steps = wrapper.get('[aria-label="Détail du calcul"]').text()
    expect(steps).toContain('1\u202F780\u00A0kcal')
    expect(steps).toContain('Activité × 1,7')
    expect(steps).toContain('+275\u00A0kcal')
    expect((field(wrapper, 'Calories').element as HTMLInputElement).value).toBe('3300')
    expect(wrapper.get('[data-testid="macro-energy"]').text()).toBe('Les macros font 3\u202F300\u00A0kcal.')

    await wrapper.findAll('form')[1]!.trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/targets')?.body).toEqual({
      effectiveFrom: TODAY, energyKcal: 3300, proteinGrams: 144, carbohydrateGrams: 474, fatGrams: 92, estimate: request,
    })
    expect(wrapper.text()).toContain('Objectifs enregistrés')
  })

  it('sends a signed change for a cut and none for maintenance', async () => {
    fake.on('GET /api/nutrition/targets', () => [])
    fake.on('POST /api/nutrition/targets/estimate', () => estimate)
    const wrapper = await mountSuspended(TargetsPage, { route: '/nutrition/objectifs' })
    await flushPromises()
    await field(wrapper, 'Poids actuel').setValue('80')

    await field(wrapper, 'Objectif').setValue('cut')
    await field(wrapper, 'Perte visée par semaine').setValue('0.5')
    await wrapper.findAll('form')[0]!.trigger('submit')
    await flushPromises()
    expect(fake.calls.at(-1)?.body).toMatchObject({ goal: 'cut', weeklyWeightChangeKg: -0.5 })

    await field(wrapper, 'Objectif').setValue('maintain')
    expect(wrapper.findAll('label').some(l => l.text().includes('visée par semaine'))).toBe(false)
    await wrapper.findAll('form')[0]!.trigger('submit')
    await flushPromises()
    expect(fake.calls.at(-1)?.body).toMatchObject({ goal: 'maintain', weeklyWeightChangeKg: 0 })
  })

  it('starts from the targets in force, and an estimate replaces them', async () => {
    fake.on('GET /api/nutrition/targets', () => [{ ...target, id: 'next', effectiveFrom: '2099-01-01', energyKcal: 2000 }, target])
    fake.on('POST /api/nutrition/targets/estimate', () => estimate)
    const wrapper = await mountSuspended(TargetsPage, { route: '/nutrition/objectifs' })
    await flushPromises()

    expect((field(wrapper, 'Calories').element as HTMLInputElement).value, 'not the targets planned later').toBe(String(target.energyKcal))
    expect((field(wrapper, 'Protéines').element as HTMLInputElement).value).toBe(String(target.proteinGrams))
    expect((field(wrapper, 'Glucides').element as HTMLInputElement).value).toBe(String(target.carbohydrateGrams))
    expect((field(wrapper, 'Lipides').element as HTMLInputElement).value).toBe(String(target.fatGrams))
    expect(wrapper.text()).toContain('Tes objectifs actuels.')
    const history = wrapper.findAll('li').filter(item => item.text().includes('depuis le'))
    expect(history.map(item => item.get('p').classes().includes('text-accent-ink')), 'the targets in force stand out, not the planned ones').toEqual([false, true])

    const saveButton = button(wrapper, 'Enregistrer mes objectifs')
    expect(saveButton.attributes('disabled'), 'nothing changed yet').toBeDefined()
    await field(wrapper, 'Calories').setValue('2900')
    expect(saveButton.attributes('disabled')).toBeUndefined()
    await field(wrapper, 'Calories').setValue(String(target.energyKcal))
    expect(saveButton.attributes('disabled'), 'typed back as it was').toBeDefined()

    await field(wrapper, 'Poids actuel').setValue('80')
    await wrapper.findAll('form')[0]!.trigger('submit')
    await flushPromises()
    expect((field(wrapper, 'Calories').element as HTMLInputElement).value).toBe('3300')
  })

  it('warns when typed macros do not add up, and shows the refusal', async () => {
    fake.on('GET /api/nutrition/targets', () => [target])
    fake.on('POST /api/nutrition/targets', () => {
      throw apiError(422, 'nutrition.inconsistent_target')
    })
    fake.on('POST /api/nutrition/targets/estimate', () => {
      throw apiError(422, 'nutrition.invalid_energy_inputs')
    })
    const wrapper = await mountSuspended(TargetsPage, { route: '/nutrition/objectifs' })
    await flushPromises()
    expect(wrapper.text()).toContain('3\u202F000\u00A0kcal')
    expect(wrapper.text()).toContain('depuis le 1 septembre 2026 · Estimation')

    await wrapper.findAll('form')[0]!.trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('ne permettent pas d’estimer tes besoins')

    await field(wrapper, 'Calories').setValue('3000')
    await field(wrapper, 'Protéines').setValue('100')
    await field(wrapper, 'Glucides').setValue('100')
    await field(wrapper, 'Lipides').setValue('50')
    expect(wrapper.get('[data-testid="macro-energy"]').text()).toContain('trop loin des calories visées')
    await wrapper.findAll('form')[1]!.trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/targets')?.body).toMatchObject({ estimate: null })
    expect(wrapper.text()).toContain('Les macros ne correspondent pas aux calories visées.')
    expect(wrapper.text()).not.toContain('Objectifs enregistrés')
  })
})

describe('meal split page', () => {
  it('keeps the total at 100 before saving', async () => {
    fake.on('GET /api/nutrition/meal-split', () => ({ breakfast: 25, lunch: 30, snack: 0, gainer: 15, dinner: 30 }))
    fake.on('PUT /api/nutrition/meal-split', body => body)
    const wrapper = await mountSuspended(SplitPage, { route: '/nutrition/repartition' })
    await flushPromises()
    expect(wrapper.get('[role="status"]').text()).toBe('Total\u00A0: 100\u00A0%')

    await field(wrapper, 'Collation').setValue('10')
    expect(wrapper.get('[data-testid="split-total"]').text()).toBe('Total\u00A0: 110\u00A0% (10\u00A0% de trop)')
    await field(wrapper, 'Collation').setValue('')
    expect(wrapper.get('[data-testid="split-total"]').text()).toBe('Total\u00A0: 100\u00A0%')
    await field(wrapper, 'Dîner').setValue('25')
    expect(wrapper.get('[data-testid="split-total"]').text()).toContain('(il manque 5\u00A0%)')
    await field(wrapper, 'Dîner').setValue('30')
    await field(wrapper, 'Collation').setValue('10')
    expect(button(wrapper, 'Enregistrer').attributes('disabled')).toBeDefined()

    await field(wrapper, 'Dîner').setValue('20')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'PUT /api/nutrition/meal-split')?.body).toEqual({ breakfast: 25, lunch: 30, snack: 10, gainer: 15, dinner: 20 })
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Répartition enregistrée.')
  })

  it('shows a refusal', async () => {
    fake.on('GET /api/nutrition/meal-split', () => ({ breakfast: 25, lunch: 30, snack: 0, gainer: 15, dinner: 30 }))
    fake.on('PUT /api/nutrition/meal-split', () => {
      throw apiError(422, 'nutrition.invalid_meal_split')
    })
    const wrapper = await mountSuspended(SplitPage, { route: '/nutrition/repartition' })
    await flushPromises()

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('La répartition doit totaliser 100 %.')
  })
})

describe('custom foods', () => {
  it('lists the user\'s foods, or explains why to create one', async () => {
    fake.on('GET /api/foods/custom', () => [skyr])
    const listed = await mountSuspended(FoodsPage, { route: '/nutrition/aliments' })
    await flushPromises()
    expect(listed.get(`a[href="/nutrition/aliments/${skyr.id}"]`).text()).toContain('Siggi’s · 63\u00A0kcal / 100\u00A0g')

    fake.on('GET /api/foods/custom', () => [])
    clearNuxtData()
    const empty = await mountSuspended(FoodsPage, { route: '/nutrition/aliments' })
    await flushPromises()
    expect(empty.text()).toContain('Aucun aliment personnel')
  })

  it('creates a food from its label, with portions', async () => {
    fake.on('POST /api/foods', () => skyr)
    const wrapper = await mountSuspended(NewFoodPage, { route: '/nutrition/aliments/nouveau' })

    await field(wrapper, 'Nom').setValue('Skyr nature')
    await field(wrapper, 'Marque').setValue('Siggi’s')
    await field(wrapper, 'Énergie').setValue('63')
    await field(wrapper, 'Protéines').setValue('11')
    await field(wrapper, 'Glucides').setValue('4')
    await field(wrapper, 'Lipides').setValue('0.2')
    await field(wrapper, 'dont sucres').setValue('4')
    await button(wrapper, 'Ajouter une portion').trigger('click')
    await field(wrapper, 'Nom de la portion 1').setValue('1 pot')
    await field(wrapper, 'Poids de la portion 1').setValue('150')
    await button(wrapper, 'Ajouter une portion').trigger('click')
    await button(wrapper, 'Retirer la portion 2').trigger('click')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.at(-1)).toMatchObject({ route: 'POST /api/foods', body: {
      name: 'Skyr nature', brand: 'Siggi’s', state: 'not_applicable', energyKcal: 63, protein: 11, carbohydrate: 4, fat: 0.2,
      fibre: null, sugars: 4, saturatedFat: null, salt: null, portions: [{ label: '1 pot', grams: 150 }],
    } })
    expect(navigate).toHaveBeenCalledWith('/nutrition/aliments')
  })

  it('shows per-field refusals', async () => {
    fake.on('POST /api/foods', () => {
      throw apiError(422, null, [{ propertyPath: 'name', message: 'Cette valeur ne doit pas être vide.' }])
    })
    const wrapper = await mountSuspended(NewFoodPage, { route: '/nutrition/aliments/nouveau' })

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Cette valeur ne doit pas être vide.')
    expect(navigate).not.toHaveBeenCalled()
  })

  it('edits and deletes one of the user\'s foods', async () => {
    fake.on('GET /api/foods/{id}', () => skyr)
    fake.on('PUT /api/foods/{id}', body => ({ ...skyr, ...(body as object) }))
    fake.on('DELETE /api/foods/{id}', () => undefined)
    const wrapper = await mountSuspended(FoodEditPage, { route: `/nutrition/aliments/${skyr.id}` })
    await flushPromises()

    expect((field(wrapper, 'Nom de la portion 1').element as HTMLInputElement).value).toBe('1 pot')
    await field(wrapper, 'Nom').setValue('Skyr vanille')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'PUT /api/foods/{id}')).toMatchObject({ body: { name: 'Skyr vanille', portions: [{ label: '1 pot', grams: 150 }] }, params: { path: { id: skyr.id } } })
    expect(navigate).toHaveBeenCalledWith('/nutrition/aliments')

    const again = await mountSuspended(FoodEditPage, { route: `/nutrition/aliments/${skyr.id}` })
    await flushPromises()
    await confirm(again, 'Supprimer cet aliment')
    await flushPromises()
    expect(fake.calls.at(-1)).toMatchObject({ route: 'DELETE /api/foods/{id}', params: { path: { id: skyr.id } } })
  })

  it('does not offer to edit a catalogue food, nor a missing one', async () => {
    fake.on('GET /api/foods/{id}', () => rice)
    const catalogue = await mountSuspended(FoodEditPage, { route: `/nutrition/aliments/${rice.id}` })
    await flushPromises()
    expect(catalogue.text()).toContain('ne se modifient pas')
    expect(catalogue.find('form').exists()).toBe(false)

    clearNuxtData()
    fake.on('GET /api/foods/{id}', () => {
      throw apiError(404, 'nutrition.food_not_found')
    })
    const missing = await mountSuspended(FoodEditPage, { route: `/nutrition/aliments/${rice.id}` })
    await flushPromises()
    expect(missing.text()).toContain('Cet aliment n’existe plus.')
  })

  it('reports a failed deletion', async () => {
    fake.on('GET /api/foods/{id}', () => skyr)
    fake.on('DELETE /api/foods/{id}', () => {
      throw apiError(404, 'nutrition.food_not_found')
    })
    const wrapper = await mountSuspended(FoodEditPage, { route: `/nutrition/aliments/${skyr.id}` })
    await flushPromises()

    await confirm(wrapper, 'Supprimer cet aliment')
    await flushPromises()

    expect(wrapper.text()).toContain('Cet aliment n’existe plus.')
    expect(navigate).not.toHaveBeenCalled()
  })
})

describe('drawer', () => {
  it('opens as a modal and closes from its button, the backdrop or Escape', async () => {
    const wrapper = await mountSuspended(Drawer, { props: { 'open': true, 'title': 'Titre', 'onUpdate:open': (value: boolean) => wrapper.setProps({ open: value }) }, slots: { default: () => 'Contenu' } })
    const dialog = wrapper.get('dialog')
    expect(dialog.attributes('open')).toBeDefined()
    expect(wrapper.text()).toContain('Contenu')

    await button(wrapper, 'Fermer').trigger('click')
    await flushPromises()
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
    expect(dialog.attributes('open')).toBeUndefined()

    await wrapper.setProps({ open: true })
    await flushPromises()
    await dialog.trigger('click')
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])

    await wrapper.setProps({ open: true })
    await flushPromises()
    await dialog.trigger('close')
    expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false])
  })
})
