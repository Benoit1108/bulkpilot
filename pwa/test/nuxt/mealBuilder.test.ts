import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Select from '~/components/ui/Select.vue'
import NutritionPage from '~/pages/nutrition/index.vue'
import { freezeToday, TODAY } from '../support/clock'
import { button } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { builderResult, chicken, day, entry, oil, rice } from '../support/nutrition'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))

mockNuxtImport('useApi', () => () => api.current!.client)

const fake = createFakeApi()
api.current = fake
// 20:00: the proposal is for dinner by default.
freezeToday(20)

beforeEach(() => {
  fake.reset()
  clearNuxtData()
  fake.on('GET /api/foods/recent', () => [rice])
  fake.on('GET /api/recipes', () => [])
  fake.on('GET /api/nutrition/favorite-meals', () => [])
  fake.on('GET /api/nutrition/averages', () => ({ days: 7, loggedDays: 0, dailyAverage: null, currentTarget: null }))
  fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
})

async function openBuilder() {
  const wrapper = await mountSuspended(NutritionPage, { route: '/nutrition' })
  await flushPromises()
  await button(wrapper, 'Que manger\u202F? Composer un repas').trigger('click')
  await flushPromises()
  return wrapper
}

const builderCalls = () => fake.calls.filter(call => call.route === 'GET /api/nutrition/meal-builder')

describe('meal builder', () => {
  it('fetches nothing until it is opened', async () => {
    fake.on('GET /api/nutrition/meal-builder', () => builderResult(TODAY))
    await mountSuspended(NutritionPage, { route: '/nutrition' })
    await flushPromises()

    expect(builderCalls()).toEqual([])
  })

  it('proposes meals for the rest of the day and logs one into the meal of the time of day', async () => {
    fake.on('GET /api/nutrition/meal-builder', () => builderResult(TODAY))
    fake.on('POST /api/nutrition/entries/batch', () => day(TODAY, [entry('dinner', 140)]))
    const wrapper = await openBuilder()

    expect(fake.client.get).toHaveBeenCalledWith('/api/nutrition/meal-builder', { query: { day: TODAY, slot: undefined } })
    expect(wrapper.get('[data-testid="remaining"]').text()).toContain('820\u00A0kcal')
    const dialog = wrapper.get('dialog[open]')
    expect(dialog.get('h3').text()).toBe('Proposition 1')
    expect(dialog.text()).toContain('Poulet, filet, sauté/poêlé · 140\u00A0g')
    expect(dialog.text()).toContain('826\u00A0kcal · Prot.\u00A051\u00A0g')

    await button(wrapper, 'Ajouter au dîner').trigger('click')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/entries/batch')?.body).toEqual({ day: TODAY, slot: 'dinner', items: [{ foodId: chicken.id, grams: 140 }, { foodId: rice.id, grams: 320 }, { foodId: oil.id, grams: 18 }] })
    expect(wrapper.find('dialog[open]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Proposition 1 ajoutée au dîner.')
  })

  it('logs into another meal when asked', async () => {
    fake.on('GET /api/nutrition/meal-builder', () => builderResult(TODAY))
    fake.on('POST /api/nutrition/entries/batch', () => day(TODAY))
    const wrapper = await openBuilder()

    await wrapper.get('dialog[open]').findAll('select')[1]!.setValue('gainer')
    await button(wrapper, 'Ajouter au gainer').trigger('click')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/entries/batch')?.body).toMatchObject({ slot: 'gainer' })
  })

  it('composes for one meal, into that meal', async () => {
    fake.on('GET /api/nutrition/meal-builder', () => builderResult(TODAY, 'lunch'))
    fake.on('POST /api/nutrition/entries/batch', () => day(TODAY))
    const wrapper = await openBuilder()

    await wrapper.get('dialog[open] select').setValue('lunch')
    await flushPromises()
    expect(fake.client.get).toHaveBeenLastCalledWith('/api/nutrition/meal-builder', { query: { day: TODAY, slot: 'lunch' } })
    expect(wrapper.get('dialog[open]').findAll('select')).toHaveLength(1)

    await button(wrapper, 'Ajouter au déjeuner').trigger('click')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/entries/batch')?.body).toMatchObject({ slot: 'lunch' })
  })

  it('recomputes on each opening', async () => {
    fake.on('GET /api/nutrition/meal-builder', () => builderResult(TODAY))
    const wrapper = await openBuilder()
    await button(wrapper, 'Fermer', 'dialog[open]').trigger('click')
    await flushPromises()

    await button(wrapper, 'Que manger\u202F? Composer un repas').trigger('click')
    await flushPromises()

    expect(builderCalls()).toHaveLength(2)
  })

  it('explains an empty result, a missing target and failures', async () => {
    fake.on('GET /api/nutrition/meal-builder', () => ({ ...builderResult(TODAY), proposals: [] }))
    const empty = await openBuilder()
    expect(empty.get('dialog[open]').text()).toContain('Rien à proposer')

    fake.on('GET /api/nutrition/meal-builder', () => ({ day: TODAY, slot: null, remaining: null, proposals: [] }))
    const noTarget = await openBuilder()
    expect(noTarget.get('dialog[open]').text()).toContain('Définis d’abord tes objectifs')

    fake.on('GET /api/nutrition/meal-builder', () => {
      throw apiError(500, null)
    })
    const failing = await openBuilder()
    expect(failing.get('dialog[open]').text()).toContain('Impossible de calculer des propositions.')
  })

  it('shows a refused proposal', async () => {
    fake.on('GET /api/nutrition/meal-builder', () => builderResult(TODAY))
    fake.on('POST /api/nutrition/entries/batch', () => {
      throw apiError(404, 'nutrition.food_not_found')
    })
    const wrapper = await openBuilder()

    await button(wrapper, 'Ajouter au dîner').trigger('click')
    await flushPromises()

    expect(wrapper.get('dialog[open] [role="alert"]').text()).toBe('Cet aliment n’existe plus.')
  })

  it('is offered only while there is enough left to eat', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, [entry('lunch', 830)]))
    const wrapper = await mountSuspended(NutritionPage, { route: '/nutrition' })
    await flushPromises()

    expect(wrapper.findAll('button').some(b => b.text().startsWith('Que manger'))).toBe(false)
  })
})

describe('select', () => {
  it('offers an empty choice unless the choice is required', async () => {
    const optional = await mountSuspended(Select, { props: { label: 'Sexe', options: [{ value: 'female', label: 'Femme' }] } })
    expect(optional.findAll('option').map(o => o.text())).toEqual(['Non renseigné', 'Femme'])

    const required = await mountSuspended(Select, { props: { label: 'Repas', options: [{ value: 'lunch', label: 'Déjeuner' }], nullable: false, modelValue: 'lunch' } })
    expect(required.findAll('option').map(o => o.text())).toEqual(['Déjeuner'])
  })
})
