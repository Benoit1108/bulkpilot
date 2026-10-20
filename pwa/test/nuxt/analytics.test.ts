import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import StrengthChart from '~/features/training/components/StrengthChart.vue'
import AnalyticsPage from '~/pages/progression/analyses.vue'
import { formatShortDay, localDay, shiftDay, weekStart } from '~/services/calendar/days'
import { button } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { summary } from '../support/progression'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)
const fake = createFakeApi()
api.current = fake
const today = localDay()

const intake = {
  period: '3m',
  days: [{ day: '2026-09-15', energyKcal: 2300, proteinGrams: 110 }, { day: '2026-09-22', energyKcal: 2500, proteinGrams: 120 }],
  weeks: [{ week: '2026-09-14', averageEnergyKcal: 2300, averageProteinGrams: 110, days: 1 }, { week: '2026-09-21', averageEnergyKcal: 2500, averageProteinGrams: 120, days: 1 }],
  averageEnergyKcal: 2400,
  averageProteinGrams: 115,
  target: { energyKcal: 2600, proteinGrams: 130 },
}
const training = {
  period: '3m',
  totals: { sessions: 6, workingSets: 72, reps: 640, tonnageKg: 31250.5, records: 4 },
  weeks: [{ week: '2026-09-14', sessions: 3, workingSets: 36, reps: 320, tonnageKg: 15000, records: 1 }, { week: '2026-09-21', sessions: 3, workingSets: 36, reps: 320, tonnageKg: 16250.5, records: 3 }],
  sessionsPerWeek: 3,
}

beforeEach(() => {
  fake.reset()
  clearNuxtData()
  fake.on('GET /api/nutrition/history', () => intake)
  fake.on('GET /api/progression/weight', () => summary({ weeks: [{ week: '2026-09-14', averageKg: 50.6, weighIns: 1 }, { week: '2026-09-21', averageKg: 51.2, weighIns: 1 }] }))
  fake.on('GET /api/training/analytics', () => training)
})

async function openPage() {
  const wrapper = await mountSuspended(AnalyticsPage, { route: '/progression/analyses' })
  await flushPromises()
  return wrapper
}

describe('analytics', () => {
  it('sets calories against weight week by week, on the same weeks', async () => {
    const wrapper = await openPage()
    const card = wrapper.findAll('section').find(section => section.text().startsWith('Calories face au poids'))!

    expect(card.text()).toContain('2\u202F300\u00A0kcal puis 2\u202F500\u00A0kcal par jour, et un poids moyen de 50,6\u00A0kg à 51,2\u00A0kg (+0,6\u00A0kg)')
    expect(card.findAll('figure')).toHaveLength(2)
    expect(card.findAll('tbody tr').map(row => row.findAll('th, td').map(cell => cell.text())), 'one table after both charts').toEqual([
      ['sem. du 21 sept.', '2\u202F500\u00A0kcal', '1', '51,2\u00A0kg', '1'],
      ['sem. du 14 sept.', '2\u202F300\u00A0kcal', '1', '50,6\u00A0kg', '1'],
    ])
    for (const call of ['GET /api/nutrition/history', 'GET /api/progression/weight', 'GET /api/training/analytics']) {
      expect(fake.calls.find(one => one.route === call)?.params).toEqual({ query: { period: '3m', today } })
    }
  })

  it('charts calories and proteins against the targets, and sums up the training', async () => {
    const wrapper = await openPage()

    expect(wrapper.text()).toContain('2\u202F400\u00A0kcal par jour en moyenne sur les 3 derniers mois, sur 2 jours saisis.')
    expect(wrapper.findAll('svg text').some(text => text.text() === 'Objectif 130\u00A0g')).toBe(true)
    expect(wrapper.text()).toContain('Records battus4')
    expect(wrapper.text()).toContain('Séances par semaine3')
    expect(wrapper.text()).toContain('Tonnage31\u202F251\u00A0kg')
    expect(wrapper.text()).toContain('31\u202F251\u00A0kg soulevés en 6 séances sur les 3 derniers mois.')
  })

  it('marks the week under way and leaves it out of the comparison', async () => {
    const monday = weekStart(today)
    const lastWeek = shiftDay(monday, -7)
    fake.on('GET /api/nutrition/history', () => ({ ...intake, weeks: [{ week: lastWeek, averageEnergyKcal: 2300, averageProteinGrams: 110, days: 7 }, { week: monday, averageEnergyKcal: 2900, averageProteinGrams: 140, days: 2 }] }))
    fake.on('GET /api/progression/weight', () => summary({ weeks: [{ week: lastWeek, averageKg: 50.6, weighIns: 5 }, { week: monday, averageKg: 51.4, weighIns: 1 }] }))
    const wrapper = await openPage()
    const card = wrapper.findAll('section').find(section => section.text().startsWith('Calories face au poids'))!

    expect(card.text()).toContain('Il faut au moins deux semaines terminées')
    expect(card.find('tbody th').text()).toBe(`sem. du ${formatShortDay(monday)} (en cours)`)
    expect(card.text(), 'its few days would pull the line').toContain('Moyenne des jours saisis de chaque semaine terminée, 1\u00A0semaine.')
    expect(card.text()).toContain('Moyenne des pesées de chaque semaine terminée, 1\u00A0semaine.')
  })

  it('draws the tonnage of the finished weeks only, and lists the week under way', async () => {
    const monday = weekStart(today)
    const lastWeek = shiftDay(monday, -7)
    fake.on('GET /api/training/analytics', () => ({ ...training, weeks: [{ ...training.weeks[0], week: lastWeek, tonnageKg: 57000 }, { ...training.weeks[1], week: monday, tonnageKg: 5000 }] }))
    const wrapper = await openPage()
    const chart = wrapper.findAll('figure').find(figure => figure.text().includes('Tonnage par semaine'))!

    expect(chart.text()).toContain('La semaine en cours apparaîtra une fois terminée.')
    expect(chart.findAll('tbody th').map(cell => cell.text())).toEqual([`sem. du ${formatShortDay(monday)} (en cours)`, `sem. du ${formatShortDay(lastWeek)}`])
    expect(chart.findAll('svg circle'), 'the finished week alone, drawn as a dot').toHaveLength(1)
  })

  it('keeps the tonnage table for a first week under way, and hides the chart without sessions', async () => {
    const monday = weekStart(today)
    fake.on('GET /api/training/analytics', () => ({ ...training, totals: { ...training.totals, sessions: 2 }, weeks: [{ ...training.weeks[0], week: monday, sessions: 2 }] }))
    const first = await openPage()
    const chart = first.findAll('figure').find(figure => figure.text().includes('Tonnage par semaine'))!
    expect(chart.findAll('svg circle'), 'nothing drawn yet').toHaveLength(0)
    expect(chart.findAll('tbody th').map(cell => cell.text())).toEqual([`sem. du ${formatShortDay(monday)} (en cours)`])
    first.unmount()

    clearNuxtData()
    fake.on('GET /api/training/analytics', () => ({ ...training, totals: { sessions: 0, workingSets: 0, reps: 0, tonnageKg: 0, records: 0 }, weeks: training.weeks.map(week => ({ ...week, sessions: 0, tonnageKg: 0 })), sessionsPerWeek: 0 }))
    const none = await openPage()
    expect(none.text()).not.toContain('Tonnage par semaine')
  })

  it('leaves the week under way out of the weekly means, and says so', async () => {
    const monday = weekStart(today)
    const lastWeek = shiftDay(monday, -7)
    const days = Array.from({ length: 70 }, (_, index) => ({ day: shiftDay(today, -index), energyKcal: 2400, proteinGrams: 120 }))
    fake.on('GET /api/nutrition/history', () => ({ ...intake, period: '6m', days, weeks: [{ week: lastWeek, averageEnergyKcal: 2400, averageProteinGrams: 120, days: 7 }, { week: monday, averageEnergyKcal: 900, averageProteinGrams: 40, days: 1 }] }))
    const wrapper = await openPage()
    const calories = wrapper.findAll('figure').find(figure => figure.text().startsWith('Calories2'))!

    expect(calories.text()).toContain('La semaine en cours apparaîtra une fois terminée.')
    expect(calories.findAll('tbody th').map(cell => cell.text())[0]).toBe(`sem. du ${formatShortDay(monday)} (en cours)`)
    expect(calories.findAll('path').filter(path => path.attributes('d')?.includes('L')), 'one finished week: no weekly line yet').toHaveLength(0)
  })

  it('names each period by what it shows, the short label first', async () => {
    const wrapper = await openPage()

    expect(wrapper.get('input[name="analytics-period"][value="1y"]').element.closest('label')!.textContent!.trim()).toBe('1\u00A0an (dernière année)')
    await wrapper.get('input[name="analytics-period"][value="all"]').setValue(true)
    await flushPromises()
    expect(wrapper.text()).toContain('soulevés en 6 séances depuis le début.')
  })

  it('shows the weekly means over the days past two months, and lists the weeks', async () => {
    const days = Array.from({ length: 70 }, (_, index) => ({ day: shiftDay('2026-06-01', index), energyKcal: 2400, proteinGrams: 120 }))
    fake.on('GET /api/nutrition/history', () => ({ ...intake, period: '6m', days, weeks: [{ week: '2026-06-01', averageEnergyKcal: 2400, averageProteinGrams: 120, days: 7 }] }))
    const wrapper = await openPage()
    const calories = wrapper.findAll('figure').find(figure => figure.text().startsWith('Calories2'))!

    expect(calories.findAll('li').map(item => item.text())).toContain('Chaque jour')
    expect(calories.findAll('thead th').map(cell => cell.text())).toEqual(['Semaine', 'Calories / jour', 'Jours saisis'])
    expect(calories.findAll('tbody tr').map(row => row.text())).toEqual(['sem. du 1 juin2\u202F400\u00A0kcal7'])
  })

  it('reads everything again for another period', async () => {
    const wrapper = await openPage()

    await wrapper.get('input[name="analytics-period"][value="1y"]').setValue(true)
    await flushPromises()

    expect(fake.calls.filter(call => call.route === 'GET /api/training/analytics').at(-1)?.params).toEqual({ query: { period: '1y', today } })
  })

  it('says when there is not enough to compare, and retries what failed', async () => {
    fake.on('GET /api/nutrition/history', () => ({ ...intake, days: [], weeks: [], averageEnergyKcal: null, averageProteinGrams: null }))
    fake.on('GET /api/training/analytics', () => {
      throw apiError(503, null)
    })
    const wrapper = await openPage()

    expect(wrapper.text()).toContain('Il faut au moins deux semaines terminées avec des repas saisis et des pesées pour comparer.')
    expect(wrapper.text()).toContain('Aucun jour saisi sur les 3 derniers mois.')
    await button(wrapper, 'Réessayer').trigger('click')
    expect(fake.calls.filter(call => call.route === 'GET /api/training/analytics')).toHaveLength(2)
  })
})

describe('the strength of an exercise', () => {
  const session = (date: string, estimatedOneRepMax: number | null, topLoadKg: number | null, bestReps: number | null = null) => ({ workoutId: date, date: `${date}T18:00:00+00:00`, workingSets: 3, topLoadKg, estimatedOneRepMax, volumeKg: 1000, bestReps, bestSeconds: null })

  it('follows the estimated 1RM and the heaviest load of a weighted exercise', async () => {
    const progress = { id: 'bench', records: [], sessions: [session('2026-09-01', 90, 75), session('2026-09-15', 95.5, 80)] }
    const wrapper = await mountSuspended(StrengthChart, { props: { progress, measurement: 'weight' } })

    expect(wrapper.text()).toContain('1RM estimé\u00A0: de 90\u00A0kg à 95,5\u00A0kg sur 2 séances.')
    expect(wrapper.findAll('figure > ul > li').map(item => item.text()), 'the legend').toEqual(['1RM estimé', 'Charge max'])
    expect(wrapper.findAll('tbody tr').map(row => row.findAll('th, td').map(cell => cell.text()))).toEqual([['15 sept.', '95,5\u00A0kg', '80\u00A0kg'], ['1 sept.', '90\u00A0kg', '75\u00A0kg']])
  })

  it('lists two sessions of the same day on their own rows', async () => {
    const progress = { id: 'bench', records: [], sessions: [session('2026-09-15', 90, 75), { ...session('2026-09-15', 92, 77.5), workoutId: 'evening' }] }
    const wrapper = await mountSuspended(StrengthChart, { props: { progress, measurement: 'weight' } })

    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
  })

  it('follows the best reps of a bodyweight exercise, and shows nothing before a session', async () => {
    const reps = await mountSuspended(StrengthChart, { props: { progress: { id: 'pull', records: [], sessions: [session('2026-09-15', null, null, 12)] }, measurement: 'bodyweight' } })
    expect(reps.text()).toContain('Une séance\u00A0: 12\u00A0reps.')

    const none = await mountSuspended(StrengthChart, { props: { progress: { id: 'pull', records: [], sessions: [] }, measurement: 'duration' } })
    expect(none.find('figure').exists()).toBe(false)
  })
})
