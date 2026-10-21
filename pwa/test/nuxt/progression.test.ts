import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProgressionPage from '~/pages/progression/index.vue'
import GoalPage from '~/pages/progression/objectif.vue'
import WeighInsPage from '~/pages/progression/pesees.vue'
import EstimateForm from '~/features/nutrition/components/EstimateForm.vue'
import { localDay } from '~/services/calendar/days'
import { button, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { goal, series, summary, weighIn } from '../support/progression'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake
const today = localDay()

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  fake.on('GET /api/progression/weight', () => summary({ latest: { day: '2026-09-28', weightKg: 51.8 } }))
  fake.on('GET /api/nutrition/history', () => intake)
  fake.on('GET /api/training/week', () => trainingWeek)
  fake.on('GET /api/progression/measurements', () => ({ period: '3m', measures: [series('neck', []), series('left_arm', [['2026-07-01', 36.5], ['2026-09-20', 37.5]])] }))
})

const intake = {
  period: '3m' as const,
  days: [],
  weeks: [{ week: '2026-09-14', averageEnergyKcal: 2550, averageProteinGrams: 140, days: 5 }, { week: '2026-09-21', averageEnergyKcal: 2650, averageProteinGrams: 146, days: 6 }],
  averageEnergyKcal: 2610,
  averageProteinGrams: 142,
  target: { energyKcal: 2500, proteinGrams: 150 },
}
const trainingWeek = {
  week: '2026-09-28',
  muscles: [{ muscle: 'chest' as const, workingSets: 14 }, { muscle: 'quads' as const, workingSets: 13 }],
  lastWeek: { week: '2026-09-21', sessions: 3, workingSets: 42, tonnageKg: 57000 },
}

async function openOverview(route = '/progression') {
  const wrapper = await mountSuspended(ProgressionPage, { route, attachTo: document.body })
  await flushPromises()
  return wrapper
}

/** A card or tile of the page, by its title (several of them hold a chart). */
function card(wrapper: Awaited<ReturnType<typeof openOverview>>, title: string) {
  const found = wrapper.findAll('section').find(section => section.find('h2').exists() && section.get('h2').text() === title)
  expect(found, title).toBeDefined()
  return found!
}

async function openWeighIn() {
  const wrapper = await openOverview()
  await button(wrapper, 'Ajouter une pesée').trigger('click')
  await flushPromises()
  return wrapper
}

describe('the weight overview', () => {
  it('shows where the weight stands and its chart', async () => {
    const wrapper = await openOverview()

    expect(fake.calls.find(call => call.route === 'GET /api/progression/weight')?.params).toEqual({ query: { period: '3m', today } })
    expect(wrapper.text()).toContain('51,8\u00A0kgle 28 septembre 2026 · sur 4 semaines\u00A0: en hausse (+0,42\u00A0kg/semaine)')
    expect(wrapper.text()).toContain('Moyenne 7 jours51,6\u00A0kg')
    expect(wrapper.text()).toContain('+4,8\u00A0kg (depuis le 1 juin 2026)')
    expect(wrapper.text()).toContain('la moyenne 7 jours passe de 50,6\u00A0kg à 51,6\u00A0kg (+1,0\u00A0kg), sur 3 pesées')
    expect(card(wrapper, 'Poids').findAll('table tbody tr')).toHaveLength(3)
    expect(wrapper.text()).toContain('Aucun objectif de poids')
    expect(wrapper.find('a[href="/progression/objectif"]').exists()).toBe(true)
  })

  it('sums up the period in tiles, and this week\'s sets by muscle', async () => {
    const wrapper = await openOverview()
    const tile = (title: string) => card(wrapper, title)

    expect(tile('Calories par jour').text()).toContain('2\u202F610\u00A0kcal')
    expect(tile('Calories par jour').text()).toContain('Objectif 2\u202F500\u00A0kcal')
    expect(tile('Protéines par jour').text()).toContain('142\u00A0g')
    expect(tile('Tonnage de la semaine dernière').text()).toContain('57\u202F000\u00A0kg')
    expect(tile('Tonnage de la semaine dernière').text()).toContain('En 3\u00A0séances, 42\u00A0séries de travail')
    expect(fake.calls.find(call => call.route === 'GET /api/training/week')?.params, 'whatever the period').toEqual({ query: { today } })
    expect(tile('Bras gauche').text(), 'the first measure taken').toContain('37,5\u00A0cm')
    expect(tile('Bras gauche').text()).toContain('+1,0\u00A0cm')
    expect(tile('Bras gauche').get('a').attributes('href')).toBe('/progression/mensurations')
    expect(wrapper.text()).toContain('Pectoraux14\u00A0séries')
    expect(wrapper.text()).toContain('Quadriceps13\u00A0séries')
  })

  it('records a weigh-in in a panel, then reads everything again', async () => {
    fake.on('PUT /api/progression/weigh-ins/{day}', body => weighIn(today, (body as { weightKg: number }).weightKg))
    const wrapper = await openWeighIn()
    expect((field(wrapper, 'Poids').element as HTMLInputElement).value, 'from the latest weigh-in').toBe('51,8')

    await field(wrapper, 'Poids').setValue('52,3')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    const puts = fake.calls.filter(call => call.route === 'PUT /api/progression/weigh-ins/{day}')
    expect(puts.map(put => [put.body, put.params])).toEqual([[{ weightKg: 52.3 }, { path: { day: today } }]])
    expect(wrapper.find('dialog[open]').exists(), 'the panel closes').toBe(false)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Pesée du jour enregistrée\u00A0: 52,3\u00A0kg.')
    expect(fake.calls.filter(call => call.route === 'GET /api/progression/weight')).toHaveLength(2)
  })

  it('says what is missing in each tile, and keeps a failure in its own tile', async () => {
    fake.on('GET /api/nutrition/history', () => ({ ...intake, weeks: [], averageEnergyKcal: null, averageProteinGrams: null, target: null }))
    fake.on('GET /api/training/week', () => ({ ...trainingWeek, muscles: [], lastWeek: { ...trainingWeek.lastWeek, sessions: 0, workingSets: 0, tonnageKg: 0 } }))
    fake.on('GET /api/progression/measurements', () => {
      throw apiError(503, null)
    })
    const wrapper = await openOverview()

    expect(card(wrapper, 'Calories par jour').text()).toContain('Aucun jour saisi sur les 3 derniers mois.')
    expect(card(wrapper, 'Tonnage de la semaine dernière').text()).toContain('Aucune séance la semaine dernière.')
    expect(card(wrapper, 'Séries par muscle, cette semaine').text(), 'the empty text, without the footnote').toMatch(/semaine\s*Aucune série de travail cette semaine pour l’instant\.$/)
    expect(card(wrapper, 'Mensurations').findAll('button').map(b => b.text())).toEqual(['Réessayer'])
    expect(wrapper.findAll('button').filter(b => b.text() === 'Réessayer'), 'only the failed tile').toHaveLength(1)
    expect(card(wrapper, 'Poids').text()).toContain('51,8\u00A0kg')
  })

  it('keeps the tiles when the weight fails to load', async () => {
    fake.on('GET /api/progression/weight', () => {
      throw apiError(503, null)
    })
    const wrapper = await openOverview()

    expect(button(wrapper, 'Réessayer')).toBeDefined()
    expect(card(wrapper, 'Calories par jour').text()).toContain('2\u202F610\u00A0kcal')
    expect(wrapper.text()).toContain('Pectoraux14\u00A0séries')
  })

  it('focuses the weight when the panel opens, and closes it without saving', async () => {
    const wrapper = await openWeighIn()

    expect(document.activeElement).toBe(field(wrapper, 'Poids').element)
    // Escape closes a modal dialog: the browser then sends it « close ».
    wrapper.get('dialog[open]').element.dispatchEvent(new Event('close'))
    await flushPromises()
    expect(fake.calls.some(call => call.route === 'PUT /api/progression/weigh-ins/{day}')).toBe(false)
    expect(wrapper.find('dialog[open]').exists()).toBe(false)
  })

  it('opens the weigh-in panel when asked from elsewhere (the global « + »), on arrival or later', async () => {
    const wrapper = await openOverview('/progression?ajouter=pesee')

    expect(wrapper.find('dialog[open]').exists()).toBe(true)
    expect(document.activeElement).toBe(field(wrapper, 'Poids').element)
    expect(navigate).toHaveBeenCalledWith({ query: {} }, { replace: true })

    wrapper.unmount()
    const already = await openOverview()
    expect(already.find('dialog[open]').exists()).toBe(false)
    await useRouter().push('/progression?ajouter=pesee')
    await flushPromises()
    expect(already.find('dialog[open]').exists(), 'the global « + » while on the page').toBe(true)
  })

  it('says a weigh-in of that day will be replaced, and shows why one is refused', async () => {
    fake.on('GET /api/progression/weight', () => summary({ latest: { day: today, weightKg: 51.8 } }))
    fake.on('PUT /api/progression/weigh-ins/{day}', () => {
      throw apiError(422, null, [{ propertyPath: 'weightKg', message: 'Entre 20 et 400.' }])
    })
    const wrapper = await openWeighIn()
    expect(wrapper.text()).toContain('Une pesée existe déjà ce jour-là\u00A0: elle sera remplacée.')

    await field(wrapper, 'Poids').setValue('')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()
    expect(field(wrapper, 'Poids').attributes('aria-invalid')).toBe('true')
  })

  it('switches the period of the chart', async () => {
    const wrapper = await openOverview()

    await wrapper.get('input[name="weight-period"][value="all"]').setValue(true)
    await flushPromises()

    expect(fake.calls.filter(call => call.route === 'GET /api/progression/weight').at(-1)?.params).toEqual({ query: { period: 'all', today } })
  })

  it('shows the goal: the way done, the milestones, the estimates', async () => {
    fake.on('GET /api/progression/weight', () => summary({ goal }))
    const wrapper = await openOverview()

    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('32')
    expect(wrapper.text()).toContain('Jalon 50,0\u00A0kgatteint le 12 septembre 2026')
    expect(wrapper.text()).toContain('+1,8\u00A0kg depuis le dernier jalon atteint')
    expect(wrapper.text()).toContain('3,2\u00A0kg à prendre — environ 8\u00A0semaines au rythme actuel (estimation statistique)')
    expect(wrapper.text()).toContain('8,2\u00A0kg à prendre — environ 20\u00A0semaines')
    expect(wrapper.findAll('svg text').some(text => text.text() === 'Objectif 60,0\u00A0kg'), 'the target on the chart').toBe(true)
  })

  it('says when nothing was weighed yet, and retries a failed load', async () => {
    fake.on('GET /api/progression/weight', () => summary({ latest: null, first: null, average7Kg: null, average30Kg: null, kgPerWeek: null, direction: null, sinceStartKg: null, points: [] }))
    const empty = await openOverview()
    expect(empty.text()).toContain('Aucune pesée pour l’instant')
    expect(empty.text(), 'a goal starts from a weigh-in').toContain('Enregistre d’abord une pesée\u00A0: elle sert de point de départ à l’objectif.')
    expect(empty.find('a[href="/progression/objectif"]').exists()).toBe(false)
    expect(card(empty, 'Poids').find('svg[role="group"]').exists()).toBe(false)

    clearNuxtData()
    fake.on('GET /api/progression/weight', () => {
      throw apiError(503, null)
    })
    const failed = await openOverview()
    expect(button(failed, 'Réessayer')).toBeDefined()
  })
})

describe('the chart', () => {
  it('reads the values of a day with the arrow keys, and announces them', async () => {
    const wrapper = await openOverview()
    const weight = card(wrapper, 'Poids')
    const chart = weight.get('svg[role="group"]')

    await chart.trigger('keydown', { key: 'End' })
    expect(weight.get('figure [role="status"]').text()).toBe('29 sept. — Pesées\u00A0: 51,8\u00A0kg, Moyenne 7 jours\u00A0: 51,6\u00A0kg, Moyenne 30 jours\u00A0: 50,9\u00A0kg')
    await chart.trigger('keydown', { key: 'ArrowLeft' })
    expect(weight.get('figure [role="status"]').text()).toContain('22 sept.')
    expect(weight.findAll('figure li').map(item => item.text())).toEqual(['Pesées', 'Moyenne 7 jours', 'Moyenne 30 jours'])
  })
})

describe('the weigh-ins', () => {
  it('lists them by pages and removes one', async () => {
    const first = Array.from({ length: 30 }, (_, i) => weighIn(`2026-08-${String(i + 1).padStart(2, '0')}`, 50 + i / 10)).reverse()
    fake.on('GET /api/progression/weigh-ins', (_, params) => ((params as { query?: { before?: string } }).query?.before ? [weighIn('2026-07-31', 49.9)] : first))
    fake.on('DELETE /api/progression/weigh-ins/{day}', () => null)
    const wrapper = await mountSuspended(WeighInsPage, { route: '/progression/pesees' })
    await flushPromises()

    expect(wrapper.findAll('li')).toHaveLength(30)
    const oldest = () => wrapper.findAll('li').at(-1)!.text()
    expect(wrapper.findAll('h2').map(h => h.text())).toEqual(['août 2026'])
    expect(wrapper.findAll('li')[0]!.text()).toContain('52,9\u00A0kg · +0,1\u00A0kg depuis la pesée d’avant30 août 2026')
    expect(oldest(), 'nothing loaded before it to compare with').toBe('50,0\u00A0kg1 août 2026Retirer')
    await button(wrapper, 'Pesées plus anciennes').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('h2').map(h => h.text())).toEqual(['août 2026', 'juillet 2026'])
    expect(oldest()).toBe('49,9\u00A0kg31 juillet 2026Retirer')
    expect(wrapper.findAll('li').at(-2)!.text(), 'compared once the one before is read').toContain('50,0\u00A0kg · +0,1\u00A0kg')
    expect(wrapper.findAll('button').some(b => b.text() === 'Pesées plus anciennes')).toBe(false)

    await button(wrapper, 'Retirer la pesée du 30 août 2026').trigger('click')
    await button(wrapper, 'Confirmer').trigger('click')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'DELETE /api/progression/weigh-ins/{day}')?.params).toEqual({ path: { day: '2026-08-30' } })
    expect(wrapper.text()).toContain('Pesée du 30 août 2026 retirée.')
    expect(fake.calls.filter(call => call.route === 'GET /api/progression/weigh-ins' && !(call.params as { query: { before?: string } }).query.before), 'read again in place').toHaveLength(2)
  })

  it('keeps the focus on the list after a removal', async () => {
    const days = [weighIn('2026-09-29', 51.8), weighIn('2026-09-28', 51.5), weighIn('2026-09-27', 51.2)]
    let listed = days
    fake.on('GET /api/progression/weigh-ins', () => listed)
    fake.on('DELETE /api/progression/weigh-ins/{day}', () => {
      listed = days.filter(one => one.day !== '2026-09-28')
      return null
    })
    const wrapper = await mountSuspended(WeighInsPage, { route: '/progression/pesees', attachTo: document.body })
    await flushPromises()

    const remove = button(wrapper, 'Retirer la pesée du 28 septembre 2026')
    expect(remove.text(), 'short visible label').toBe('Retirer')
    await remove.trigger('click')
    await button(wrapper, 'Confirmer').trigger('click')
    await flushPromises()
    expect(document.activeElement?.getAttribute('aria-label'), 'the next weigh-in').toBe('Retirer la pesée du 27 septembre 2026')
    wrapper.unmount()
  })
})

describe('the weight goal', () => {
  it('sets a first goal with milestones', async () => {
    fake.on('GET /api/progression/weight-goal', () => {
      throw apiError(404, 'progression.weight_goal_not_found')
    })
    fake.on('PUT /api/progression/weight-goal', () => goal)
    const wrapper = await mountSuspended(GoalPage, { route: '/progression/objectif' })
    await flushPromises()

    await field(wrapper, 'Poids visé').setValue('60')
    await button(wrapper, 'Ajouter un jalon').trigger('click')
    await field(wrapper, 'Jalon 1').setValue('55')
    await button(wrapper, 'Ajouter un jalon').trigger('click')
    await field(wrapper, 'Jalon 2').setValue('50,5')
    await button(wrapper, 'Ajouter un jalon').trigger('click')
    await button(wrapper, 'Retirer le jalon 3').trigger('click')
    await button(wrapper, 'Suivre cet objectif').trigger('click')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'PUT /api/progression/weight-goal')?.body).toEqual({ targetKg: 60, milestonesKg: [55, 50.5] })
    expect(navigate).toHaveBeenCalledWith('/progression')
  })

  it('edits the goal followed, or stops following it', async () => {
    fake.on('GET /api/progression/weight-goal', () => goal)
    fake.on('GET /api/progression/weigh-ins', () => [weighIn('2026-09-29', 51.8)])
    fake.on('DELETE /api/progression/weight-goal', () => null)
    const wrapper = await mountSuspended(GoalPage, { route: '/progression/objectif' })
    await flushPromises()

    expect((field(wrapper, 'Poids visé').element as HTMLInputElement).value).toBe('60')
    expect((field(wrapper, 'Jalon 1').element as HTMLInputElement).value, 'only the milestones still ahead').toBe('55')
    expect(wrapper.findAll('label').some(label => label.text() === 'Jalon 2')).toBe(false)
    expect(wrapper.text()).toContain('Départ\u00A0: ta dernière pesée, 51,8\u00A0kg le 29 septembre 2026.')
    expect(button(wrapper, 'Remplacer l’objectif')).toBeDefined()

    await button(wrapper, 'Ne plus suivre cet objectif').trigger('click')
    await button(wrapper, 'Confirmer').trigger('click')
    await flushPromises()
    expect(fake.calls.map(call => call.route)).toContain('DELETE /api/progression/weight-goal')
    expect(navigate).toHaveBeenCalledWith('/progression')
  })
})

describe('the nutrition estimate', () => {
  it('starts from the latest weigh-in', async () => {
    fake.on('GET /api/progression/weigh-ins', () => [weighIn('2026-09-28', 62.4)])
    const wrapper = await mountSuspended(EstimateForm, { props: { account: null } })
    await flushPromises()

    expect((field(wrapper, 'Poids actuel').element as HTMLInputElement).value).toBe('62,4')
    expect(wrapper.text()).toContain('Ta dernière pesée (28 septembre 2026).')

    await field(wrapper, 'Poids actuel').setValue('63')
    expect(wrapper.text(), 'no longer the weigh-in').not.toContain('Ta dernière pesée')
  })
})
