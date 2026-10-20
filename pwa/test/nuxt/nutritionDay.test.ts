import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DaySummary from '~/features/nutrition/components/DaySummary.vue'
import { formatDayIn, formatLongDay, shiftDay, weekStart } from '~/services/calendar/days'
import NutritionPage from '~/pages/nutrition/index.vue'
import { freezeToday, TODAY } from '../support/clock'
import { button, buttonStartingWith, field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { day, entry, rice, skyr, target } from '../support/nutrition'

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
  fake.on('GET /api/recipes', () => [])
  fake.on('GET /api/nutrition/favorite-meals', () => [])
  fake.on('GET /api/nutrition/averages', () => ({ days: 7, loggedDays: 0, dailyAverage: null, currentTarget: null }))
})

async function openPage(route = '/nutrition') {
  const wrapper = await mountSuspended(NutritionPage, { route })
  await flushPromises()
  return wrapper
}

describe('day summary', () => {
  it('shows what is left, then a neutral overflow', async () => {
    const left = await mountSuspended(DaySummary, { props: { day: day(TODAY, [entry('lunch', 500)]) } })
    expect(left.get('[data-testid="energy-gap"]').text()).toBe('Encore 1\u202F240\u00A0kcal')
    expect(left.text()).toContain('1\u202F760\u00A0kcal sur 3\u202F000\u00A0kcal')
    expect(left.findAll('[role="progressbar"]').map(bar => bar.attributes('aria-label')), 'each macro in its bar').toEqual(['Protéines', 'Glucides', 'Lipides'])

    const over = await mountSuspended(DaySummary, { props: { day: day(TODAY, [entry('lunch', 1000)]) } })
    expect(over.get('[data-testid="energy-gap"]').text()).toBe('Au-delà de l’objectif +520\u00A0kcal')
    const [drawn, around] = over.findAll('circle').at(-1)!.attributes('stroke-dasharray')!.split(' ').map(Number)
    expect(drawn, 'the ring closed, never past its round').toBe(around)

    const exact = await mountSuspended(DaySummary, { props: { day: { ...day(TODAY), totals: { energyKcal: 3000, protein: 0, carbohydrate: 0, fat: 0, fibre: null } } } })
    expect(exact.get('[data-testid="energy-gap"]').text()).toBe('Objectif atteint')
  })

  it('invites to set targets when the day has none, without an empty progress bar', async () => {
    const wrapper = await mountSuspended(DaySummary, { props: { day: day(TODAY, [], null) } })

    expect(wrapper.find('[data-testid="energy-gap"]').exists()).toBe(false)
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.get('a[href="/nutrition/objectifs"]').text()).toBe('Définir mes objectifs')
  })
})

describe('nutrition page', () => {
  it('lists every meal of today with its share of the target', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, [entry('lunch', 150)]))
    const wrapper = await openPage()

    expect(fake.calls.find(call => call.route === 'GET /api/nutrition/days/{day}')?.params).toMatchObject({ path: { day: TODAY } })
    expect(wrapper.get('[data-testid="day-label"]').text()).toBe('Aujourd’hui')
    const lunch = wrapper.get('[data-testid="meal-lunch"]')
    expect(lunch.text()).toContain('Riz blanc, cru')
    expect(lunch.text()).toContain('sur 900\u00A0kcal')
    expect(button(wrapper, 'Jour suivant').attributes('aria-disabled'), 'stays focusable').toBe('true')
    expect(wrapper.findAll('button').some(b => b.text() === 'Aujourd’hui')).toBe(false)
  })

  it('opens the day of the URL and navigates between days, keeping the focus on the day', async () => {
    const yesterday = shiftDay(TODAY, -1)
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    const wrapper = await mountSuspended(NutritionPage, { route: `/nutrition?jour=${yesterday}`, attachTo: document.body })
    await flushPromises()

    expect(wrapper.get('[data-testid="day-label"]').text(), 'the real date, not only « Hier »').toBe(formatLongDay(yesterday))
    await button(wrapper, 'Jour précédent').trigger('click')
    expect(navigate).toHaveBeenCalledWith({ query: { jour: shiftDay(TODAY, -2) } })
    await button(wrapper, 'Jour suivant').trigger('click')
    expect(navigate).toHaveBeenLastCalledWith({ query: {} })
    await button(wrapper, 'Aujourd’hui').trigger('click')
    await flushPromises()
    expect(navigate).toHaveBeenLastCalledWith({ query: {} })
    expect(document.activeElement).toBe(wrapper.get('[data-testid="day-label"]').element)
    wrapper.unmount()
  })

  it('shows the week of the day, a dot under each day with meals, and opens a day in one tap', async () => {
    // A Wednesday of last week, shown: its week reaches back nine days, beyond « 7d ».
    const shown = shiftDay(TODAY, -5)
    const monday = weekStart(shown)
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('GET /api/nutrition/history', () => ({ period: '30d', days: [{ day: monday, energyKcal: 2400, proteinGrams: 140 }], weeks: [], averageEnergyKcal: 2400, averageProteinGrams: 140, target: { energyKcal: 3000, proteinGrams: 150 } }))
    const wrapper = await openPage(`/nutrition?jour=${shown}`)

    expect(fake.calls.find(call => call.route === 'GET /api/nutrition/history')?.params).toEqual({ query: { period: '30d', today: TODAY } })
    const days = wrapper.findAll('ol[aria-label="Jours de la semaine"] button')
    expect(days.map(cell => cell.attributes('aria-label')).filter(label => label?.endsWith('repas saisis')), 'the Monday the history has').toEqual([`${formatDayIn(monday, TODAY)}, repas saisis`])
    expect(days.find(cell => cell.attributes('aria-current') === 'date')!.attributes('aria-label')).toBe(formatDayIn(shown, TODAY))
    expect(days.filter(cell => cell.attributes('aria-disabled') === 'true'), 'last week: nothing to come').toHaveLength(0)

    await days[0]!.trigger('click')
    expect(navigate).toHaveBeenLastCalledWith({ query: { jour: monday } })
    const week = wrapper.findAll('section').find(section => section.text().startsWith('Cette semaine'))!
    expect(week.findAll('tbody td').map(cell => cell.text())[0]).toBe('2\u202F400\u00A0kcal')
  })

  it('marks the days of the current week to come, and refreshes the week once food is logged', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('GET /api/nutrition/history', () => ({ period: '7d', days: [], weeks: [], averageEnergyKcal: null, averageProteinGrams: null, target: null }))
    fake.on('POST /api/nutrition/entries', () => day(TODAY, [entry('lunch', 150)]))
    const wrapper = await openPage()

    const days = wrapper.findAll('ol[aria-label="Jours de la semaine"] button')
    const shown = days.findIndex(cell => cell.attributes('aria-current') === 'date')
    expect(days.filter(cell => cell.attributes('aria-disabled') === 'true')).toHaveLength(6 - shown)
    const loads = () => fake.calls.filter(call => call.route === 'GET /api/nutrition/history').length
    const before = loads()

    await useNutritionDay(ref(TODAY)).log({ foodId: rice.id }, 'lunch', 150)
    await flushPromises()
    expect(loads(), 'the week read again, in place').toBeGreaterThan(before)
  })

  it('does not go past today', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    const wrapper = await openPage()

    await button(wrapper, 'Jour suivant').trigger('click')

    expect(navigate.mock.calls.filter(([to]) => !(to as { path?: string }).path), 'no day navigation').toEqual([])
  })

  it('keeps the previous day on screen, inert, while the next one loads', async () => {
    let answer: (value: unknown) => void = () => {}
    fake.on('GET /api/nutrition/days/{day}', (_, params) => {
      if (params!.path!.day === TODAY) return day(TODAY, [entry('lunch', 150)])
      return new Promise((resolve) => {
        answer = resolve
      })
    })
    const wrapper = await openPage()
    expect(wrapper.find('[data-testid="meal-lunch"]').text()).toContain('Riz')

    await useRouter().push(`/nutrition?jour=${shiftDay(TODAY, -1)}`)
    await flushPromises()

    // Today's meals stay (the page keeps its height) but can't be used: nothing is written to the wrong day.
    const shown = wrapper.get('[data-testid="meal-lunch"]')
    expect(shown.text()).toContain('Riz')
    expect(shown.element.closest('[inert]'), 'inert while yesterday loads').not.toBeNull()
    expect(shown.element.closest('[aria-busy="true"]')).not.toBeNull()
    expect(wrapper.text()).toContain('Chargement…')
    answer(day(shiftDay(TODAY, -1)))
    await flushPromises()
    expect(wrapper.get('[data-testid="meal-lunch"]').text()).not.toContain('Riz')
    expect(wrapper.get('[data-testid="meal-lunch"]').element.closest('[inert]')).toBeNull()
  })

  it('offers a retry when the day cannot be loaded', async () => {
    let fail = true
    fake.on('GET /api/nutrition/days/{day}', (_, params) => {
      if (fail) throw apiError(500, null)
      return day(params!.path!.day!)
    })
    const wrapper = await openPage()
    expect(wrapper.text()).toContain('Impossible de charger cette journée.')

    fail = false
    await button(wrapper, 'Réessayer').trigger('click')
    await flushPromises()
    expect(wrapper.find('[data-testid="meal-breakfast"]').exists()).toBe(true)
  })

  it('logs a recent food with one of its portions, then confirms it', async () => {
    fake.on('GET /api/foods/recent', () => [skyr])
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('POST /api/nutrition/entries', body => day(TODAY, [entry('breakfast', (body as { grams: number }).grams, skyr)]))
    const wrapper = await openPage()

    await button(wrapper, 'Ajouter un aliment au petit-déjeuner').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[open] h2').text()).toBe('Ajouter au petit-déjeuner')
    expect(wrapper.text()).toContain('Récents')
    await buttonStartingWith(wrapper, 'Skyr nature', 'dialog[open]').trigger('click')
    expect((field(wrapper, 'Quantité').element as HTMLInputElement).value).toBe('150')
    expect(wrapper.get('dialog[open] [aria-pressed="true"]').text()).toBe('1 pot (150\u00A0g)')
    expect(wrapper.get('dialog[open] [data-testid="quantity-preview"]').text()).toContain('95\u00A0kcal')

    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/entries')?.body).toEqual({ foodId: skyr.id, day: TODAY, slot: 'breakfast', grams: 150 })
    expect(wrapper.get('[data-testid="meal-breakfast"]').text()).toContain('Skyr nature')
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Skyr nature ajouté au petit-déjeuner.')
  })

  /** A journal that keeps what is logged, with skyr eaten lately (150 g last time). */
  async function openQuickAdd(log: (body: { grams: number }) => unknown = () => undefined) {
    let logged: ReturnType<typeof entry>[] = []
    fake.on('GET /api/foods/recent', () => [{ ...skyr, lastGrams: 150 }])
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, logged))
    fake.on('POST /api/nutrition/entries', async (body) => {
      await log(body as { grams: number })
      logged = [...logged, entry('breakfast', (body as { grams: number }).grams, skyr)]
      return day(TODAY, logged)
    })
    const wrapper = await openPage()
    await button(wrapper, 'Ajouter un aliment au petit-déjeuner').trigger('click')
    await flushPromises()
    return wrapper
  }
  const QUICK_SKYR = 'Ajouter Skyr nature, 150 g'
  const entriesPosted = () => fake.calls.filter(call => call.route === 'POST /api/nutrition/entries').map(call => call.body)

  it('adds a recent food again with its last quantity in one tap, the drawer staying open', async () => {
    const wrapper = await openQuickAdd()

    expect(wrapper.get('dialog[open] [aria-label="Récents"]').text()).toContain('150 g · 95 kcal')
    await button(wrapper, QUICK_SKYR, 'dialog[open]').trigger('click')
    await flushPromises()
    await button(wrapper, QUICK_SKYR, 'dialog[open]').trigger('click')
    await flushPromises()

    expect(entriesPosted()).toEqual([
      { foodId: skyr.id, day: TODAY, slot: 'breakfast', grams: 150 },
      { foodId: skyr.id, day: TODAY, slot: 'breakfast', grams: 150 },
    ])
    expect(wrapper.get('dialog[open] [data-testid="quick-status"]').text()).toBe('Skyr nature ajouté au petit-déjeuner (150 g).')
    await button(wrapper, 'Fermer', 'dialog[open]').trigger('click')
    await flushPromises()
    expect(wrapper.find('dialog[open]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('2 aliments ajoutés au petit-déjeuner.')
  })

  it('ignores a second tap while the first add is on its way', async () => {
    let release = () => {}
    const wrapper = await openQuickAdd(() => new Promise<void>((resolve) => {
      release = resolve
    }))

    await button(wrapper, QUICK_SKYR, 'dialog[open]').trigger('click')
    expect(button(wrapper, QUICK_SKYR, 'dialog[open]').attributes('aria-disabled')).toBe('true')
    await button(wrapper, QUICK_SKYR, 'dialog[open]').trigger('click')
    release()
    await flushPromises()

    expect(entriesPosted()).toHaveLength(1)
    expect(button(wrapper, QUICK_SKYR, 'dialog[open]').attributes('aria-disabled')).toBeUndefined()
  })

  it('still says an add closed over before it was saved, in the meal it went to', async () => {
    let release = () => {}
    const wrapper = await openQuickAdd(() => new Promise<void>((resolve) => {
      release = resolve
    }))

    await button(wrapper, QUICK_SKYR, 'dialog[open]').trigger('click')
    await button(wrapper, 'Fermer', 'dialog[open]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-testid="status"]').text()).toBe('')
    release()
    await flushPromises()

    expect(wrapper.get('[data-testid="status"]').text()).toBe('Skyr nature ajouté au petit-déjeuner (150 g).')
    await button(wrapper, 'Ajouter un aliment au déjeuner').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[open] [data-testid="quick-status"]').text(), 'nothing left over from the last opening').toBe('')
  })

  it('says a quick add that failed, and leaves it out of the summary', async () => {
    const wrapper = await openQuickAdd(() => {
      throw apiError(500, 'internal')
    })

    await button(wrapper, QUICK_SKYR, 'dialog[open]').trigger('click')
    await flushPromises()

    expect(wrapper.find('dialog[open] [role="alert"]').exists()).toBe(true)
    expect(wrapper.get('dialog[open] [data-testid="quick-status"]').text()).toBe('')
    await button(wrapper, 'Fermer', 'dialog[open]').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-testid="status"]').text()).toBe('')
  })

  it('opens a recent food at its last quantity, and sums up the quick adds before it', async () => {
    const wrapper = await openQuickAdd()

    await button(wrapper, QUICK_SKYR, 'dialog[open]').trigger('click')
    await flushPromises()
    const row = buttonStartingWith(wrapper, 'Skyr nature', 'dialog[open]')
    expect(row.text(), 'the line says what touching it does').toMatch(/, régler la quantité$/)
    await row.trigger('click')
    expect((field(wrapper, 'Quantité').element as HTMLInputElement).value, 'the quantity its line shows').toBe('150')
    await field(wrapper, 'Quantité').setValue('200')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(entriesPosted().map(body => (body as { grams: number }).grams)).toEqual([150, 200])
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Skyr nature ajouté au petit-déjeuner (150 g). Skyr nature ajouté au petit-déjeuner.')
  })

  it('has no quick add for a food found by its name: its line sets the quantity', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'] })
    vi.setSystemTime(new Date(2026, 8, 28, 12, 0))
    fake.on('GET /api/foods', () => [rice])
    const wrapper = await openQuickAdd()

    await field(wrapper, 'Rechercher un aliment ou une recette').setValue('riz')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()

    expect(wrapper.get('dialog[open] [aria-label="Aliments"]').text()).toContain('Riz blanc, cru')
    expect(wrapper.findAll('dialog[open] button').some(b => b.attributes('aria-label')?.startsWith('Ajouter Riz'))).toBe(false)
    vi.useRealTimers()
  })

  it('reads quantities typed with a decimal comma', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('POST /api/nutrition/entries', () => day(TODAY))
    const wrapper = await openPage()
    await button(wrapper, 'Ajouter un aliment au déjeuner').trigger('click')
    await flushPromises()
    await buttonStartingWith(wrapper, 'Riz blanc', 'dialog[open]').trigger('click')

    await field(wrapper, 'Quantité').setValue('12,5')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(fake.calls.find(call => call.route === 'POST /api/nutrition/entries')?.body).toMatchObject({ grams: 12.5 })
  })

  it('says when a quantity cannot be read, and sends nothing', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    const wrapper = await openPage()
    await button(wrapper, 'Ajouter un aliment au déjeuner').trigger('click')
    await flushPromises()
    await buttonStartingWith(wrapper, 'Riz blanc', 'dialog[open]').trigger('click')

    await field(wrapper, 'Quantité').setValue('beaucoup')
    await wrapper.get('dialog[open] form').trigger('submit')

    expect(wrapper.get('dialog[open]').text()).toContain('Indique une quantité')
    expect(fake.calls.some(call => call.route === 'POST /api/nutrition/entries')).toBe(false)
  })

  it('searches as the user types, and an older, slower answer never replaces a newer one', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'] })
    vi.setSystemTime(new Date(2026, 8, 28, 12, 0))
    const pending: Record<string, (foods: unknown) => void> = {}
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('GET /api/foods', (_, params) => new Promise((resolve) => {
      pending[String((params as { query?: { q?: string } }).query?.q)] = resolve
    }))
    const wrapper = await openPage()
    await button(wrapper, 'Ajouter un aliment au déjeuner').trigger('click')
    await flushPromises()
    const search = field(wrapper, 'Rechercher un aliment ou une recette')

    await search.setValue('r')
    await vi.advanceTimersByTimeAsync(300)
    expect(pending).toEqual({})

    await search.setValue('riz')
    await vi.advanceTimersByTimeAsync(300)
    await search.setValue('riz cuit')
    await vi.advanceTimersByTimeAsync(300)
    pending['riz cuit']!([{ ...rice, name: 'Riz blanc, cuit' }])
    await flushPromises()
    pending.riz!([rice])
    await flushPromises()

    const dialog = wrapper.get('dialog[open]')
    expect(dialog.text()).toContain('Riz blanc, cuit')
    expect(dialog.text()).not.toContain('Riz blanc, cru')
    expect(dialog.get('[data-testid="search-status"]').text()).toBe('1\u00A0résultat')
    vi.useRealTimers()
  })

  it('says when nothing was found, or when the search failed', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'] })
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('GET /api/foods', () => [])
    const wrapper = await openPage()
    await button(wrapper, 'Ajouter un aliment au dîner').trigger('click')
    await flushPromises()
    const search = field(wrapper, 'Rechercher un aliment ou une recette')

    await search.setValue('introuvable')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()
    expect(wrapper.get('dialog[open] [data-testid="search-status"]').text()).toBe('Aucun résultat pour «\u00A0introuvable\u00A0».')

    fake.on('GET /api/foods', () => {
      throw apiError(503, null)
    })
    await search.setValue('panne')
    await vi.advanceTimersByTimeAsync(300)
    await flushPromises()
    expect(wrapper.get('dialog[open] [data-testid="search-status"]').text()).toContain('La recherche a échoué')

    await search.setValue('')
    await flushPromises()
    expect(wrapper.get('dialog[open]').text()).toContain('Récents')
    vi.useRealTimers()
  })

  it('shows why a quantity was refused, and forgets it when another food is picked', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('POST /api/nutrition/entries', () => {
      throw apiError(422, 'nutrition.invalid_quantity')
    })
    const wrapper = await openPage()
    await button(wrapper, 'Ajouter un aliment au dîner').trigger('click')
    await flushPromises()
    await buttonStartingWith(wrapper, 'Riz blanc', 'dialog[open]').trigger('click')
    expect((field(wrapper, 'Quantité').element as HTMLInputElement).value).toBe('100')

    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('dialog[open] [role="alert"]').text()).toContain('entre 0 et 5 000 g')

    await button(wrapper, 'Choisir autre chose').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[open]').text()).toContain('Récents')
    expect(wrapper.find('dialog[open] [role="alert"]').exists()).toBe(false)
  })

  it('corrects, moves and removes an entry, the removal being confirmed', async () => {
    const logged = entry('lunch', 100)
    let entries = [logged]
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, params!.path!.day === TODAY ? entries : []))
    fake.on('PUT /api/nutrition/entries/{id}', (body) => {
      const change = body as { grams: number, slot: 'dinner' }
      entries = [{ ...logged, grams: change.grams, slot: change.slot }]
      return day(TODAY, entries)
    })
    fake.on('DELETE /api/nutrition/entries/{id}', () => {
      entries = []
    })
    const wrapper = await openPage()

    await wrapper.get('[data-testid="meal-lunch"] li button').trigger('click')
    await flushPromises()
    expect(wrapper.get('dialog[open] h2').text()).toBe('Modifier l’entrée')
    expect(wrapper.get('dialog[open] [data-testid="quantity-preview"]').text()).toContain('352\u00A0kcal')
    await field(wrapper, 'Quantité').setValue('200')
    await wrapper.get('dialog[open] select').setValue('dinner')
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()
    expect(fake.calls.find(call => call.route === 'PUT /api/nutrition/entries/{id}')).toMatchObject({ body: { day: TODAY, slot: 'dinner', grams: 200 }, params: { path: { id: logged.id } } })
    expect(wrapper.get('[data-testid="meal-dinner"]').text()).toContain('200\u00A0g')

    await wrapper.get('[data-testid="meal-dinner"] li button').trigger('click')
    await flushPromises()
    await button(wrapper, 'Supprimer l’entrée').trigger('click')
    expect(fake.calls.some(call => call.route === 'DELETE /api/nutrition/entries/{id}'), 'the first tap only asks').toBe(false)
    await button(wrapper, 'Confirmer la suppression').trigger('click')
    await flushPromises()
    expect(wrapper.get('[data-testid="meal-dinner"]').text()).not.toContain('Riz')
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Riz blanc, cru retiré au dîner.')
  })

  it('reloads the day, and the averages, when an entry moved to another day', async () => {
    const logged = entry('lunch', 100)
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, [logged]))
    fake.on('PUT /api/nutrition/entries/{id}', () => day(shiftDay(TODAY, -1), [logged]))
    const wrapper = await openPage()
    const loads = (route: string) => fake.calls.filter(call => call.route === route).length
    const days = loads('GET /api/nutrition/days/{day}')
    const averages = loads('GET /api/nutrition/averages')

    await wrapper.get('[data-testid="meal-lunch"] li button').trigger('click')
    await flushPromises()
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()

    expect(loads('GET /api/nutrition/days/{day}')).toBe(days + 1)
    expect(loads('GET /api/nutrition/averages')).toBe(averages + 1)
  })

  it('shows the averages of the logged days against the target', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    fake.on('GET /api/nutrition/averages', () => ({ days: 30, loggedDays: 12, dailyAverage: { energyKcal: 2800, protein: 140, carbohydrate: 350, fat: 90, fibre: null }, currentTarget: target }))
    const wrapper = await openPage()

    await wrapper.get('input[name="averages-period"][value="30"]').setValue(true)
    await flushPromises()

    expect(fake.client.get).toHaveBeenCalledWith('/api/nutrition/averages', { query: { days: 30, today: TODAY } })
    expect(wrapper.text()).toContain('Moyenne par jour des 12\u00A0jours renseignés sur les 30\u00A0derniers (aujourd’hui non compris).')
    expect(wrapper.text()).toContain('2\u202F800\u00A0kcal / 3\u202F000\u00A0kcal')
  })

  it('says when nothing was logged over the period, and when averages fail', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!))
    const wrapper = await openPage()
    expect(wrapper.text()).toContain('Rien de renseigné sur les 7\u00A0derniers jours.')

    clearNuxtData()
    fake.on('GET /api/nutrition/averages', () => {
      throw apiError(500, null)
    })
    const failing = await openPage()
    expect(failing.text()).toContain('Impossible de calculer les moyennes.')
  })

  it('keeps the confirmation of the last action, then lets it go', async () => {
    vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'] })
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, [entry('snack', 150, skyr)]))
    fake.on('POST /api/nutrition/favorite-meals', () => ({}))
    const wrapper = await openPage()
    await button(wrapper, 'Enregistrer « Collation » comme repas favori').trigger('click')
    await flushPromises()
    await wrapper.get('dialog[open] form').trigger('submit')
    await flushPromises()
    expect(wrapper.get('[data-testid="status"]').text()).toBe('« Collation » est dans tes repas favoris.')

    await vi.advanceTimersByTimeAsync(6000)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('')
    vi.useRealTimers()
  })

  it('asks before deleting nothing: leaving the confirm button disarms it', async () => {
    fake.on('GET /api/nutrition/days/{day}', (_, params) => day(params!.path!.day!, [entry('lunch', 100)]))
    const wrapper = await openPage()
    await wrapper.get('[data-testid="meal-lunch"] li button').trigger('click')
    await flushPromises()

    await button(wrapper, 'Supprimer l’entrée').trigger('click')
    await button(wrapper, 'Confirmer la suppression').trigger('blur')

    expect(wrapper.findAll('button').some(b => b.text() === 'Confirmer la suppression')).toBe(false)
  })
})
