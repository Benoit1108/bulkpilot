import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MeasurementsPage from '~/pages/progression/mensurations.vue'
import OwnMeasuresPage from '~/pages/progression/mesures.vue'
import { localDay } from '~/services/calendar/days'
import { button, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { HIPS, measures, series } from '../support/progression'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)
const fake = createFakeApi()
api.current = fake
const today = localDay()

beforeEach(() => {
  fake.reset()
  clearNuxtData()
  fake.on('GET /api/progression/measures', () => measures)
  fake.on('GET /api/progression/measurements', () => ({
    period: '3m',
    measures: [
      series('waist', [['2026-08-01', 78], ['2026-09-20', 79.2]]),
      series('left_arm', [['2026-09-20', 35]]),
      series('neck', []),
      series(HIPS, [['2026-09-20', 95]]),
    ],
  }))
  fake.on('GET /api/progression/measurements/{day}', () => {
    throw apiError(404, 'progression.measurements_not_found')
  })
})

async function openPage() {
  const wrapper = await mountSuspended(MeasurementsPage, { route: '/progression/mensurations' })
  await flushPromises()
  return wrapper
}

describe('measurements', () => {
  it('offers every measure, with its last value as a hint', async () => {
    const wrapper = await openPage()

    expect(wrapper.findAll('input[inputmode="decimal"]')).toHaveLength(11)
    expect(field(wrapper, 'Hanches')).toBeDefined()
    expect(wrapper.text()).toContain('Dernière\u00A0: 79,2\u00A0cm (20 sept.)')
    expect(fake.calls.find(call => call.route === 'GET /api/progression/measurements')?.params).toEqual({ query: { period: '3m', today } })
  })

  it('records only the measures filled in', async () => {
    fake.on('PUT /api/progression/measurements/{day}', body => ({ day: today, centimetres: (body as { centimetres: Record<string, number> }).centimetres, recordedAt: `${today}T08:00:00+00:00` }))
    const wrapper = await openPage()

    await field(wrapper, 'Taille').setValue('79,5')
    await field(wrapper, 'Hanches').setValue('94')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    const put = fake.calls.find(call => call.route === 'PUT /api/progression/measurements/{day}')
    expect([put?.body, put?.params]).toEqual([{ centimetres: { waist: 79.5, [HIPS]: 94 } }, { path: { day: today } }])
    expect(wrapper.text()).toContain('Mensurations enregistrées (2).')
    expect(fake.calls.filter(call => call.route === 'GET /api/progression/measurements')).toHaveLength(2)
  })

  it('shows the measurements already taken on the day picked, to replace them', async () => {
    fake.on('GET /api/progression/measurements/{day}', (_, params) => ((params as { path: { day: string } }).path.day === '2026-09-20'
      ? { day: '2026-09-20', centimetres: { waist: 79.2, left_arm: 35 }, recordedAt: '2026-09-20T08:00:00+00:00' }
      : (() => {
          throw apiError(404, 'progression.measurements_not_found')
        })()))
    const wrapper = await openPage()

    await field(wrapper, 'Jour').setValue('2026-09-20')
    await flushPromises()

    expect((field(wrapper, 'Taille').element as HTMLInputElement).value).toBe('79,2')
    expect(wrapper.text()).toContain('les enregistrer les remplace')
    expect(field(wrapper, 'Jour').attributes('aria-describedby'), 'the hint is the day field\'s').toBeDefined()
  })

  it('ignores the answer of a day no longer picked', async () => {
    let answerSlowDay: (value: unknown) => void = () => {}
    fake.on('GET /api/progression/measurements/{day}', (_, params) => ((params as { path: { day: string } }).path.day === '2026-09-20'
      ? new Promise((resolve) => {
          answerSlowDay = resolve
        })
      : (() => {
          throw apiError(404, 'progression.measurements_not_found')
        })()))
    const wrapper = await openPage()

    await field(wrapper, 'Jour').setValue('2026-09-20')
    await field(wrapper, 'Jour').setValue('2026-09-21')
    await flushPromises()
    answerSlowDay({ day: '2026-09-20', centimetres: { waist: 79.2 }, recordedAt: '2026-09-20T08:00:00+00:00' })
    await flushPromises()

    expect((field(wrapper, 'Taille').element as HTMLInputElement).value).toBe('')
    expect(wrapper.text()).not.toContain('les enregistrer les remplace')
  })

  it('lists each measure taken with its change, and charts the one picked', async () => {
    const wrapper = await openPage()

    const rows = wrapper.findAll('tbody tr').map(row => row.text())
    expect(rows).toEqual(['Taille79,2\u00A0cm+1,2\u00A0cm', 'Bras gauche35,0\u00A0cm—', 'Hanches95,0\u00A0cm—'])
    expect(wrapper.get('figure').text()).toContain('Taille\u00A0: +1,2\u00A0cm sur les 3 derniers mois, 79,2\u00A0cm à la dernière mesure, le 20 septembre 2026.')

    await field(wrapper, 'Courbe de').setValue(HIPS)
    expect(wrapper.get('figure').text()).toContain('Hanches')
  })
})

describe('own measures', () => {
  it('adds, renames and archives them', async () => {
    fake.on('POST /api/progression/measures', body => ({ key: 'new', name: (body as { name: string }).name, custom: true, archived: false }))
    fake.on('PUT /api/progression/measures/{key}', body => ({ key: HIPS, name: (body as { name: string }).name, custom: true, archived: false }))
    fake.on('DELETE /api/progression/measures/{key}', () => null)
    const wrapper = await mountSuspended(OwnMeasuresPage, { route: '/progression/mesures' })
    await flushPromises()

    await field(wrapper, 'Nouvelle mesure').setValue('Poignet')
    await button(wrapper, 'Ajouter la mesure').trigger('click')
    await wrapper.findAll('form').at(-1)!.trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'POST /api/progression/measures')?.body).toEqual({ name: 'Poignet' })
    expect(wrapper.text()).toContain('Mesure «\u00A0Poignet\u00A0» ajoutée.')

    await field(wrapper, 'Nom de la mesure Hanches').setValue('Tour de hanches')
    await button(wrapper, 'Renommer Hanches').trigger('click')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'PUT /api/progression/measures/{key}')).toMatchObject({ body: { name: 'Tour de hanches' }, params: { path: { key: HIPS } } })

    expect(button(wrapper, 'Retirer Hanches').text(), 'short visible label').toBe('Retirer')
    await button(wrapper, 'Retirer Hanches').trigger('click')
    await button(wrapper, 'Confirmer').trigger('click')
    await flushPromises()
    expect(fake.calls.map(call => call.route)).toContain('DELETE /api/progression/measures/{key}')
    expect(wrapper.text()).toContain('ses valeurs sont gardées')
  })
})
