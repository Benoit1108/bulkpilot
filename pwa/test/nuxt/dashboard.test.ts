import 'fake-indexeddb/auto'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardPage from '~/pages/index.vue'
import { localDay } from '~/services/calendar/days'
import { button, choice } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { analytics, layout, nextWorkout } from '../support/dashboard'
import { coach, increase, recommendation, report } from '../support/coach'
import { day, entry, target } from '../support/nutrition'
import { goal, summary } from '../support/progression'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake
const today = localDay()

const averages = (days: number, loggedDays: number) => ({
  days,
  loggedDays,
  dailyAverage: loggedDays ? { energyKcal: 2800, protein: 140, carbohydrate: 350, fat: 80, fibre: null } : null,
  currentTarget: target,
})

async function settle(): Promise<void> {
  for (let i = 0; i < 5; i++) {
    await flushPromises()
    await new Promise(resolve => setTimeout(resolve, 0))
  }
}

async function openDashboard() {
  const wrapper = await mountSuspended(DashboardPage, { route: '/', attachTo: document.body })
  await settle()
  return wrapper
}

const widget = (wrapper: Awaited<ReturnType<typeof openDashboard>>, title: string) => {
  const found = wrapper.findAll('section').find(section => section.find('h2').exists() && section.get('h2').text() === title)
  expect(found, title).toBeDefined()
  return found!
}
const titles = (wrapper: Awaited<ReturnType<typeof openDashboard>>) => wrapper.findAll('section h2').map(h2 => h2.text())

/** A user with a bit of everything. */
function withData(): void {
  fake.on('GET /api/me/dashboard', () => layout())
  fake.on('GET /api/progression/weight', () => summary())
  fake.on('GET /api/nutrition/days/{day}', () => day(today, [entry('breakfast', 100), entry('lunch', 200)]))
  fake.on('GET /api/nutrition/averages', (_, params) => averages((params as { query: { days: number } }).query.days, 5))
  fake.on('GET /api/training/next-workout', () => nextWorkout)
  fake.on('GET /api/training/analytics', (_, params) => analytics(3, 2, (params as { query: { period: '7d' | '30d' } }).query.period))
  fake.on('POST /api/coach/analysis', () => coach({ recommendations: [increase, recommendation()], report: report() }))
  fake.on('GET /api/progression/weight-goal', () => goal)
}

/** A new account: nothing recorded yet. */
function empty(): void {
  fake.on('GET /api/me/dashboard', () => layout())
  fake.on('GET /api/progression/weight', () => summary({ latest: null, first: null, points: [], kgPerWeek: null, direction: null }))
  fake.on('GET /api/nutrition/days/{day}', () => day(today, [], null))
  fake.on('GET /api/training/next-workout', () => {
    throw apiError(404, 'training.no_active_program')
  })
  fake.on('GET /api/training/analytics', () => analytics(0, 0))
  fake.on('POST /api/coach/analysis', () => coach({ recommendations: [], report: null, adherence: null }))
  fake.on('GET /api/progression/weight-goal', () => {
    throw apiError(404, 'progression.weight_goal_not_found')
  })
}

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
})

describe('the dashboard', () => {
  it('shows the widgets of the day in the account\'s order, each with what the server computed', async () => {
    withData()
    const wrapper = await openDashboard()

    expect(titles(wrapper)).toEqual(['Poids', 'Nutrition', 'Repas', 'Entraînement', 'Coach', 'Objectif de poids', 'Records'])
    expect(widget(wrapper, 'Poids').text()).toContain('51,8\u00A0kg')
    expect(widget(wrapper, 'Poids').text()).toContain('Tendance\u00A0: en hausse (+0,42\u00A0kg/semaine)')
    // The week's change, as the server has it (spec §13 « variation hebdomadaire »).
    expect(widget(wrapper, 'Poids').text()).toContain('+1,2\u00A0kg sur 7 jours')
    expect(fake.calls.find(call => call.route === 'GET /api/progression/weight')?.params).toMatchObject({ query: { period: '7d' } })
    const nutrition = widget(wrapper, 'Nutrition')
    expect(nutrition.get('[role="progressbar"]').attributes('aria-label')).toBe('Calories')
    expect(nutrition.text()).toContain('sur 3\u202F000\u00A0kcal')
    expect(nutrition.findAll('dt').map(dt => dt.text())).toEqual(['Protéines', 'Glucides', 'Lipides'])
    expect(widget(wrapper, 'Repas').findAll('li').map(li => li.text())).toEqual([expect.stringMatching(/^Petit-déjeuner/), expect.stringMatching(/^Déjeuner/)])
    expect(widget(wrapper, 'Entraînement').text()).toContain('Pull')
    expect(widget(wrapper, 'Entraînement').text()).toContain('PPL · 5 exercices')
    expect(button(wrapper, 'Démarrer Pull').exists()).toBe(true)
    expect(widget(wrapper, 'Coach').text()).toContain(`Prioritaire\u00A0: ${increase.title}`)
    expect(widget(wrapper, 'Coach').text()).toContain('Et 1 autre recommandation dans le Coach.')
    // A proposal is answered in the Coach.
    expect(widget(wrapper, 'Coach').findAll('a').map(a => a.text())).toContain('Voir la proposition')
    expect(wrapper.text()).not.toContain('Pour commencer')
    expect(widget(wrapper, 'Objectif de poids').get('[role="progressbar"]').attributes('aria-valuenow')).toBe(String(goal.percent))
    expect(widget(wrapper, 'Records').text()).toContain('2 records battus ces 7 derniers jours')
    // The day view asks for the day itself, the week of analytics.
    expect(fake.calls.find(call => call.route === 'GET /api/nutrition/days/{day}')?.params).toMatchObject({ path: { day: today } })
    expect(fake.calls.some(call => call.route === 'GET /api/nutrition/averages')).toBe(false)
  })

  it('gives a new account its first steps instead of empty widgets', async () => {
    empty()
    const wrapper = await openDashboard()

    const start = widget(wrapper, 'Pour commencer')
    expect(start.findAll('a').map(a => [a.text(), a.attributes('href')])).toEqual([
      ['Fixer ton objectif calorique', '/nutrition/objectifs'],
      ['Enregistrer ta première pesée', '/progression?ajouter=pesee'],
      ['Choisir un programme d’entraînement', '/training/programmes'],
      ['Fixer un objectif de poids', '/progression/objectif'],
    ])
    expect(titles(wrapper)).toEqual(['Pour commencer'])
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows the steps left and the widgets once one is done, each empty one saying how to start', async () => {
    empty()
    fake.on('GET /api/nutrition/days/{day}', () => day(today, []))
    const wrapper = await openDashboard()

    expect(widget(wrapper, 'Pour commencer').findAll('a').map(a => a.text())).not.toContain('Fixer ton objectif calorique')
    expect(widget(wrapper, 'Poids').get('a[href="/progression?ajouter=pesee"]').text()).toBe('Ajouter une pesée')
    expect(widget(wrapper, 'Nutrition').text()).toContain('Rien de saisi aujourd’hui.')
    // The empty day is offered to fill once, in the Nutrition widget.
    expect(widget(wrapper, 'Repas').text()).toContain('Aucun repas saisi aujourd’hui.')
    expect(widget(wrapper, 'Repas').findAll('a').map(a => a.text())).not.toContain('Ajouter un aliment')
    expect(widget(wrapper, 'Entraînement').get('a[href="/training/programmes"]').text()).toBe('Choisir un programme')
    expect(widget(wrapper, 'Coach').text()).toContain('Rien à signaler')
    expect(widget(wrapper, 'Objectif de poids').findAll('a').map(a => a.text())).toContain('Fixer un objectif')
    expect(widget(wrapper, 'Records').text()).toContain('Aucun record battu ces 7 derniers jours.')
  })

  it('points the Coach\'s advice to where it applies, and a day without target to setting one', async () => {
    withData()
    fake.on('GET /api/nutrition/days/{day}', () => day(today, [entry('breakfast', 100)], null))
    fake.on('POST /api/coach/analysis', () => coach({ recommendations: [recommendation()], report: null }))
    const wrapper = await openDashboard()

    const nutrition = widget(wrapper, 'Nutrition')
    expect(nutrition.find('[role="progressbar"]').exists()).toBe(false)
    expect(nutrition.get('a[href="/nutrition/objectifs"]').text()).toBe('Fixer mon objectif')
    expect(widget(wrapper, 'Coach').findAll('a').map(a => a.attributes('href'))).toContain('/progression?ajouter=pesee')
  })

  it('sums up the last 7 days in the week view, with the weekly report and without the day\'s meals', async () => {
    withData()
    const wrapper = await openDashboard()
    await choice(wrapper, 'Semaine (ces 7 derniers jours)').setValue(true)
    await settle()

    expect(titles(wrapper)).not.toContain('Repas')
    expect(widget(wrapper, 'Nutrition').text()).toContain('Moyenne par jour, sur 5 jours saisis ces 7 derniers jours')
    expect(widget(wrapper, 'Entraînement').text()).toContain('3 séances ces 7 derniers jours')
    expect(wrapper.text()).toContain('Semaine du 21 septembre')
    expect(widget(wrapper, 'Poids').text()).toContain('+1,2\u00A0kg ces 7 derniers jours')
    expect(widget(wrapper, 'Coach').text()).toContain(increase.title)
    expect(fake.calls.find(call => call.route === 'GET /api/nutrition/averages')?.params).toMatchObject({ query: { days: 7, today } })
  })

  it('reads the last 30 days in the month view', async () => {
    withData()
    const wrapper = await openDashboard()
    await choice(wrapper, 'Mois (ces 30 derniers jours)').setValue(true)
    await settle()

    expect(fake.calls.find(call => call.route === 'GET /api/nutrition/averages')?.params).toMatchObject({ query: { days: 30 } })
    expect(fake.calls.some(call => call.route === 'GET /api/training/analytics' && (call.params as { query: { period: string } }).query.period === '30d')).toBe(true)
    expect(fake.calls.some(call => call.route === 'GET /api/progression/weight' && (call.params as { query: { period: string } }).query.period === '30d')).toBe(true)
    expect(widget(wrapper, 'Records').text()).toContain('ces 30 derniers jours')
    // Over the month: the month's weight change, the daily averages, the sessions and the report.
    expect(widget(wrapper, 'Poids').text()).toContain('+1,2\u00A0kg ces 30 derniers jours')
    expect(widget(wrapper, 'Nutrition').text()).toContain('Moyenne par jour, sur 5 jours saisis ces 30 derniers jours')
    expect(widget(wrapper, 'Entraînement').text()).toContain('3 séances ces 30 derniers jours')
    expect(titles(wrapper)).not.toContain('Repas')

    // Back to the day: the next workout, the day's meals.
    await choice(wrapper, 'Jour (aujourd’hui)').setValue(true)
    await settle()
    expect(button(wrapper, 'Démarrer Pull').exists()).toBe(true)
    expect(titles(wrapper)).toContain('Repas')
  })

  it('keeps the widgets of a newer server in the layout, without showing them', async () => {
    withData()
    fake.on('GET /api/me/dashboard', () => ({ widgets: [{ key: 'sleep' as never, visible: true }, ...layout().widgets] }))
    const wrapper = await openDashboard()

    expect(titles(wrapper)[0]).toBe('Poids')
  })

  it('keeps a failure inside its widget, without taking the focus, and loads it again on demand', async () => {
    withData()
    let weightFails = true
    fake.on('GET /api/progression/weight', () => {
      if (weightFails) throw apiError(503, null)
      return summary()
    })
    const wrapper = await openDashboard()

    // Said politely (several may fail at once offline), the focus left where it is.
    const weight = widget(wrapper, 'Poids')
    expect(weight.text()).toContain('Impossible de charger')
    expect(weight.find('[role="alert"]').exists()).toBe(false)
    expect(weight.find('[role="status"]').exists()).toBe(true)
    expect(document.activeElement?.closest('section')).not.toBe(weight.element)
    expect(widget(wrapper, 'Nutrition').text()).not.toContain('Impossible de charger')

    weightFails = false
    await button(wrapper, 'Réessayer', 'section').trigger('click')
    await settle()
    expect(widget(wrapper, 'Poids').text()).toContain('51,8\u00A0kg')
  })

  it('shows only the widgets the user keeps, and says so when none is left', async () => {
    withData()
    fake.on('GET /api/me/dashboard', () => layout(['weight', 'nutrition', 'meals', 'workout', 'coach', 'goal', 'records', 'report']))
    const wrapper = await openDashboard()

    expect(titles(wrapper)).toEqual([])
    expect(wrapper.text()).toContain('Aucun widget affiché pour cette période')
  })

  it('cannot load its layout: offers to try again', async () => {
    fake.on('GET /api/me/dashboard', () => {
      throw apiError(503, null)
    })
    const wrapper = await openDashboard()

    expect(wrapper.get('[role="alert"]').text()).toContain('Impossible de charger')
  })
})
