import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CoachPage from '~/pages/coach/index.vue'
import HistoryPage from '~/pages/coach/historique.vue'
import ReportsPage from '~/pages/coach/rapports.vue'
import SettingsPage from '~/pages/coach/reglages.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { localDay } from '~/services/calendar/days'
import { button, buttonStartingWith, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { coach, increase, recommendation, report, settings } from '../support/coach'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)
const fake = createFakeApi()
api.current = fake
const today = localDay()

beforeEach(() => {
  fake.reset()
  clearNuxtData()
  fake.on('POST /api/coach/analysis', () => coach())
})

async function openCoach() {
  const wrapper = await mountSuspended(CoachPage, { route: '/coach', attachTo: document.body })
  await flushPromises()
  return wrapper
}

describe('the Coach', () => {
  it('runs the day\'s analysis and shows what it offers, the adherence and the last report', async () => {
    const wrapper = await openCoach()

    expect(fake.calls[0]).toMatchObject({ route: 'POST /api/coach/analysis', body: { today, again: false } })
    const cards = wrapper.findAll('[data-recommendation]')
    expect(cards).toHaveLength(2)
    expect(cards[0]!.get('h3').text()).toContain('Prioritaire')
    expect(cards[0]!.text()).toContain('2\u202F350 → 2\u202F500\u00A0kcal par jour, à partir de demain')
    expect(cards[0]!.text()).toContain('Protéines et lipides inchangés, glucides ajustés (environ +38\u00A0g).')
    expect(cards[0]!.get('summary').text()).toBe('Pourquoi\u00A0?')
    expect(cards[0]!.findAll('details li').map(li => li.text())).toEqual(increase.why)
    expect(cards[0]!.findAll('button').map(b => b.text())).toEqual([
      `Accepter\u00A0: ${increase.title}`, `Refuser\u00A0: ${increase.title}`, `Plus tard\u00A0: ${increase.title}`,
    ])
    // A piece of advice leads to where to act on it, then « C'est noté » or « Plus tard ».
    expect(cards[1]!.get('h3').text()).toContain('Information')
    expect(cards[1]!.get('a').text()).toBe('Ajouter une pesée')
    expect(cards[1]!.get('a').attributes('href')).toBe('/progression?ajouter=pesee')
    expect(cards[1]!.findAll('button').map(b => b.text().split('\u00A0:')[0])).toEqual(['C’est noté', 'Plus tard'])

    expect(wrapper.text()).toContain('Calories\u00A0: 5 jours sur 7 à ±10\u00A0% de l’objectif')
    expect(wrapper.text()).toContain('Entraînement\u00A0: 2 séances sur 3 prévues')
    expect(wrapper.text()).toContain('Sur les 7 derniers jours, aujourd’hui à part.')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('75')

    expect(wrapper.text()).toContain('Semaine du 21 septembre')
    expect(wrapper.text()).toContain('47,8 → 48,2\u00A0kg (+0,4\u00A0kg)')
    expect(wrapper.text()).toContain('Progression conforme')
    expect(wrapper.text()).toContain('Continuer le plan actuel')
    expect(wrapper.find('a[href="/coach/rapports"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Rien ne change sans ton accord.')
    wrapper.unmount()
  })

  it('introduces itself and waits for data on the first run', async () => {
    fake.on('POST /api/coach/analysis', () => coach({ report: null, adherence: { score: null, parts: [{ part: 'weigh_ins', done: 0, expected: 3, score: 0 }] } }))
    const wrapper = await openCoach()
    expect(wrapper.text()).toContain('Le Coach analyse tes données chaque jour. Rien ne change sans ton accord.')
    expect(wrapper.text()).toContain('Ton adhérence s’affichera après quelques jours de saisie.')
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('accepts a calorie proposal, reads the Coach again in place and refreshes the nutrition target', async () => {
    fake.on('POST /api/coach/recommendations/{id}/decision', () => ({ ...increase, status: 'accepted' }))
    fake.on('GET /api/coach', () => coach({ recommendations: [recommendation()] }))
    await useAsyncData(NUTRITION_KEYS.targets, () => Promise.resolve([]))
    await useAsyncData(NUTRITION_KEYS.recipes, () => Promise.resolve([]))
    const wrapper = await openCoach()

    await buttonStartingWith(wrapper, 'Accepter').trigger('click')
    await flushPromises()

    const decision = fake.calls.find(call => call.route === 'POST /api/coach/recommendations/{id}/decision')
    expect(decision?.body).toEqual({ decision: 'accept', today })
    expect(decision?.params).toEqual({ path: { id: increase.id } })
    expect(fake.calls.filter(call => call.route === 'GET /api/coach').map(call => call.params)).toEqual([{ query: { today } }])
    expect(wrapper.findAll('[data-recommendation]')).toHaveLength(1)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Objectif passé à 2\u202F500\u00A0kcal à partir de demain.')
    expect(document.activeElement?.textContent, 'the one that took its place').toContain('Pèse-toi plus souvent')
    expect(useNuxtData(NUTRITION_KEYS.targets).data.value, 'the target changed').toBeUndefined()
    expect(useNuxtData(NUTRITION_KEYS.recipes).data.value, 'the rest is untouched').toEqual([])
    wrapper.unmount()
  })

  it('leaves the nutrition data alone when a proposal is refused, and says when it comes back after « Plus tard »', async () => {
    fake.on('POST /api/coach/recommendations/{id}/decision', body => ((body as { decision: string }).decision === 'reject'
      ? { ...increase, status: 'rejected' }
      : recommendation({ status: 'postponed', postponedUntil: '2026-10-01T10:00:00+00:00' })))
    fake.on('GET /api/coach', () => coach({ recommendations: [recommendation()] }))
    await useAsyncData(NUTRITION_KEYS.targets, () => Promise.resolve([]))
    const wrapper = await openCoach()

    await buttonStartingWith(wrapper, 'Refuser').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Proposition refusée, ton objectif ne change pas.')
    expect(useNuxtData(NUTRITION_KEYS.targets).data.value).toEqual([])

    await buttonStartingWith(wrapper, 'Plus tard').trigger('click')
    await flushPromises()
    expect(fake.calls.filter(call => call.route === 'POST /api/coach/recommendations/{id}/decision').map(call => (call.body as { decision: string }).decision)).toEqual(['reject', 'postpone'])
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Mise de côté jusqu’au 1 octobre 2026.')
    wrapper.unmount()
  })

  it('moves focus to the section title when the last recommendation leaves', async () => {
    fake.on('POST /api/coach/analysis', () => coach({ recommendations: [recommendation()] }))
    fake.on('POST /api/coach/recommendations/{id}/decision', () => ({ ...recommendation(), status: 'accepted' }))
    fake.on('GET /api/coach', () => coach({ recommendations: [] }))
    const wrapper = await openCoach()

    await buttonStartingWith(wrapper, 'C’est noté').trigger('click')
    await flushPromises()

    expect(document.activeElement?.textContent?.trim()).toBe('Recommandations')
    wrapper.unmount()
  })

  it('records one answer at a time', async () => {
    let answer: (value: unknown) => void = () => {}
    fake.on('POST /api/coach/recommendations/{id}/decision', () => new Promise((resolve) => {
      answer = resolve
    }))
    fake.on('GET /api/coach', () => coach({ recommendations: [recommendation()] }))
    const wrapper = await openCoach()

    await buttonStartingWith(wrapper, 'Accepter').trigger('click')
    await buttonStartingWith(wrapper, 'C’est noté').trigger('click')
    await button(wrapper, 'Relancer l’analyse').trigger('click')
    answer({ ...increase, status: 'accepted' })
    await flushPromises()

    expect(fake.calls.filter(call => call.route === 'POST /api/coach/recommendations/{id}/decision')).toHaveLength(1)
    expect(fake.calls.filter(call => call.route === 'POST /api/coach/analysis')).toHaveLength(1)
    expect(wrapper.findAll('[data-recommendation]')).toHaveLength(1)
    wrapper.unmount()
  })

  it('says why a decision is refused, in the card', async () => {
    fake.on('POST /api/coach/recommendations/{id}/decision', () => {
      throw apiError(409, 'coach.target_changed')
    })
    const wrapper = await openCoach()

    await buttonStartingWith(wrapper, 'Accepter').trigger('click')
    await flushPromises()

    expect(wrapper.findAll('[data-recommendation]')[0]!.get('[role="alert"]').text()).toBe('Ton objectif a changé depuis\u00A0: relance l’analyse.')
    wrapper.unmount()
  })

  it('says when the Coach cannot be read again after an answer', async () => {
    fake.on('POST /api/coach/recommendations/{id}/decision', () => ({ ...increase, status: 'accepted' }))
    fake.on('GET /api/coach', () => {
      throw apiError(503, null)
    })
    const wrapper = await openCoach()

    await buttonStartingWith(wrapper, 'Accepter').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('')
    wrapper.unmount()
  })

  it('runs the analysis again when asked, and says what it found', async () => {
    fake.on('POST /api/coach/analysis', body => ((body as { again: boolean }).again ? coach({ recommendations: [], report: null }) : coach()))
    const wrapper = await openCoach()

    await button(wrapper, 'Relancer l’analyse').trigger('click')
    await flushPromises()

    expect(fake.calls.filter(call => call.route === 'POST /api/coach/analysis').map(call => call.body)).toEqual([{ today, again: false }, { today, again: true }])
    expect(wrapper.text()).toContain('Rien à signaler\u00A0: continue ton plan.')
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Analyse relancée\u00A0: rien à signaler.')
    expect(wrapper.text()).not.toContain('Semaine du')
    wrapper.unmount()
  })

  it('says when running the analysis again fails', async () => {
    fake.on('POST /api/coach/analysis', (body) => {
      if ((body as { again: boolean }).again) throw apiError(429, null)
      return coach()
    })
    const wrapper = await openCoach()

    await button(wrapper, 'Relancer l’analyse').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.findAll('[data-recommendation]')).toHaveLength(2)
    wrapper.unmount()
  })

  it('retries a failed analysis', async () => {
    let fail = true
    fake.on('POST /api/coach/analysis', () => {
      if (fail) throw apiError(503, null)
      return coach()
    })
    const wrapper = await openCoach()
    fail = false
    await button(wrapper, 'Réessayer').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('[data-recommendation]')).toHaveLength(2)
    wrapper.unmount()
  })
})

describe('the history', () => {
  const items = (wrapper: Awaited<ReturnType<typeof mountSuspended>>) => wrapper.findAll('ul > li[tabindex]')

  it('lists the recommendations by pages, with their status and why', async () => {
    const first = Array.from({ length: 20 }, (_, i) => recommendation({ id: `0199a0b1-0000-7000-8000-0000000c1${String(i).padStart(3, '0')}`, createdAt: `2026-09-${String(29 - i).padStart(2, '0')}T08:00:00+00:00` }))
    fake.on('GET /api/coach/recommendations', (_, params) => ((params as { query?: { before?: string } }).query?.before
      ? [{ ...increase, status: 'accepted', decidedAt: '2026-09-09T09:00:00+00:00' }, recommendation({ id: '0199a0b1-0000-7000-8000-00000000c0ff', status: 'rejected' })]
      : first))
    const wrapper = await mountSuspended(HistoryPage, { route: '/coach/historique', attachTo: document.body })
    await flushPromises()

    expect(items(wrapper)).toHaveLength(20)
    expect(items(wrapper)[0]!.text()).toContain('En attente')
    expect(items(wrapper)[0]!.get('summary').text()).toBe('Pourquoi\u00A0?')
    await button(wrapper, 'Voir plus').trigger('click')
    await flushPromises()

    // Several are made in the same second: the cursor is the time and the id of the last one.
    expect(fake.calls.at(-1)?.params).toEqual({ query: { limit: 20, before: '2026-09-10T08:00:00+00:00', beforeId: first.at(-1)!.id } })
    expect(items(wrapper)[20]!.text()).toContain('Acceptée le 9 septembre 2026')
    expect(items(wrapper)[20]!.text()).toContain('2\u202F350 → 2\u202F500\u00A0kcal par jour')
    expect(items(wrapper)[20]!.text()).not.toContain('à partir de demain')
    expect(items(wrapper)[21]!.text(), 'an advice ignored').toContain('Ignorée')
    expect(document.activeElement, 'the first one added').toBe(items(wrapper)[20]!.element)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('2\u00A0recommandations de plus.')
    expect(wrapper.findAll('button').some(b => b.text() === 'Voir plus')).toBe(false)
    wrapper.unmount()
  })

  it('says when the next page cannot be read', async () => {
    const first = Array.from({ length: 20 }, (_, i) => recommendation({ id: `0199a0b1-0000-7000-8000-0000000c2${String(i).padStart(3, '0')}` }))
    fake.on('GET /api/coach/recommendations', (_, params) => {
      if ((params as { query?: { before?: string } }).query?.before) throw apiError(503, null)
      return first
    })
    const wrapper = await mountSuspended(HistoryPage, { route: '/coach/historique' })
    await flushPromises()
    await button(wrapper, 'Voir plus').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(items(wrapper)).toHaveLength(20)
  })

  it('says when there is nothing yet, or when it cannot be read', async () => {
    fake.on('GET /api/coach/recommendations', () => [])
    const wrapper = await mountSuspended(HistoryPage, { route: '/coach/historique' })
    await flushPromises()
    expect(wrapper.text()).toContain('Aucune recommandation pour l’instant')

    clearNuxtData()
    fake.on('GET /api/coach/recommendations', () => {
      throw apiError(503, null)
    })
    const failed = await mountSuspended(HistoryPage, { route: '/coach/historique' })
    await flushPromises()
    expect(button(failed, 'Réessayer')).toBeDefined()
  })
})

describe('the reports', () => {
  it('lists the weekly reports, or explains when the first one comes', async () => {
    fake.on('GET /api/coach/reports', () => [report(), report({ weekStart: '2026-09-14', verdict: 'below', action: 'Mange jusqu’à ta cible chaque jour.' })])
    const wrapper = await mountSuspended(ReportsPage, { route: '/coach/rapports' })
    await flushPromises()
    expect(wrapper.findAll('article')).toHaveLength(2)
    expect(wrapper.text()).toContain('Plus lent que visé')
    expect(wrapper.text()).toContain('Mange jusqu’à ta cible chaque jour.')

    clearNuxtData()
    fake.on('GET /api/coach/reports', () => [])
    const empty = await mountSuspended(ReportsPage, { route: '/coach/rapports' })
    await flushPromises()
    expect(empty.text()).toContain('Aucun rapport pour l’instant')
  })

  it('offers to try again when they cannot be read', async () => {
    fake.on('GET /api/coach/reports', () => {
      throw apiError(503, null)
    })
    const wrapper = await mountSuspended(ReportsPage, { route: '/coach/rapports' })
    await flushPromises()
    expect(button(wrapper, 'Réessayer')).toBeDefined()
  })
})

describe('the settings', () => {
  it('saves the tone and a signed pace typed the French way', async () => {
    fake.on('GET /api/coach/settings', () => settings)
    fake.on('PUT /api/coach/settings', body => body)
    const wrapper = await mountSuspended(SettingsPage, { route: '/coach/reglages' })
    await flushPromises()

    await wrapper.get('input[name="personality"][value="encouraging"]').setValue(true)
    await field(wrapper, 'Rythme visé').setValue('−0,25')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.filter(call => call.route === 'PUT /api/coach/settings').map(call => call.body)).toEqual([{ personality: 'encouraging', paceKgPerWeek: -0.25 }])
    expect(wrapper.text()).toContain('Réglages du Coach enregistrés.')
  })

  it('sends no pace when the field is blank, and shows a refused one', async () => {
    fake.on('GET /api/coach/settings', () => ({ personality: 'direct', paceKgPerWeek: 0.5 }))
    fake.on('PUT /api/coach/settings', () => {
      throw apiError(422, null, [{ propertyPath: 'paceKgPerWeek', message: 'Entre −1 et 1.' }])
    })
    const wrapper = await mountSuspended(SettingsPage, { route: '/coach/reglages' })
    await flushPromises()
    expect((field(wrapper, 'Rythme visé').element as HTMLInputElement).value).toBe('+0,5')

    await field(wrapper, 'Rythme visé').setValue('')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'PUT /api/coach/settings')?.body).toEqual({ personality: 'direct', paceKgPerWeek: null })
    expect(wrapper.text()).toContain('Entre −1 et 1.')
  })

  it('never clears the pace because of something unreadable, and sends it as typed', async () => {
    fake.on('GET /api/coach/settings', () => ({ personality: 'direct', paceKgPerWeek: 0.5 }))
    fake.on('PUT /api/coach/settings', body => body)
    const wrapper = await mountSuspended(SettingsPage, { route: '/coach/reglages' })
    await flushPromises()

    await field(wrapper, 'Rythme visé').setValue('0,5 kg')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.some(call => call.route === 'PUT /api/coach/settings')).toBe(false)
    expect(wrapper.text()).toContain('Un nombre de kilos par semaine, par exemple +0,5 ou −0,3.')

    await field(wrapper, 'Rythme visé').setValue('0,255')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'PUT /api/coach/settings')?.body, 'the server says it is not to the hundredth').toEqual({ personality: 'direct', paceKgPerWeek: 0.255 })
  })

  it('names each tone by its label and describes it apart', async () => {
    fake.on('GET /api/coach/settings', () => settings)
    const wrapper = await mountSuspended(SettingsPage, { route: '/coach/reglages' })
    await flushPromises()
    const radio = wrapper.get('input[value="direct"]')
    expect(wrapper.get(`#${radio.attributes('aria-describedby')}`).text()).toBe('Des consignes courtes, sans détour.')
    expect(wrapper.get('fieldset').attributes('aria-describedby')).toBeTruthy()
  })

  it('offers to try again when they cannot be read', async () => {
    fake.on('GET /api/coach/settings', () => {
      throw apiError(503, null)
    })
    const wrapper = await mountSuspended(SettingsPage, { route: '/coach/reglages' })
    await flushPromises()
    expect(button(wrapper, 'Réessayer')).toBeDefined()
  })
})
