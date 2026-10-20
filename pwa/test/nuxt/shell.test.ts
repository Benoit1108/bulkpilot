import 'fake-indexeddb/auto'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DefaultLayout from '~/layouts/default.vue'
import NutritionPage from '~/pages/nutrition/index.vue'
import MeasurementsPage from '~/pages/progression/mensurations.vue'
import PhotosPage from '~/pages/progression/photos.vue'
import ProgressionPage from '~/pages/progression/index.vue'
import NotificationBell from '~/components/shell/NotificationBell.vue'
import QuickActions from '~/components/shell/QuickActions.vue'
import SearchPalette from '~/components/shell/SearchPalette.vue'
import { FOOD_SEARCH_DELAY_MS } from '~/features/search/useSearch'
import { field } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { day, recipe, rice, skyr } from '../support/nutrition'
import { nextWorkout } from '../support/dashboard'
import { measures, summary } from '../support/progression'
import { bench, ppl, workoutExercise } from '../support/training'
import { resetWorkoutOutbox } from '~/features/training/outbox'
import { resetWorkoutSession } from '~/features/training/session'
import { offlineStore } from '~/services/offline/store'
import { localDay } from '~/services/calendar/days'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake

async function settle(): Promise<void> {
  for (let i = 0; i < 5; i++) {
    await flushPromises()
    if (vi.isFakeTimers()) await vi.advanceTimersByTimeAsync(0)
    else await new Promise(resolve => setTimeout(resolve, 0))
  }
}

afterEach(() => {
  vi.useRealTimers()
})

async function forgetWorkouts(): Promise<void> {
  resetWorkoutOutbox()
  resetWorkoutSession()
  await (await offlineStore()).clear()
}

beforeEach(async () => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  await forgetWorkouts()
  useState('notifications-unread').value = 0
  fake.on('GET /api/recipes', () => [recipe])
  fake.on('GET /api/exercises', () => [bench])
  fake.on('GET /api/programs', () => [ppl])
  fake.on('GET /api/workouts', () => [{ id: 'w1', dayName: 'Pull', startedAt: '2026-09-12T08:00:00Z', finishedAt: '2026-09-12T09:00:00Z', exercises: 5, workingSets: 15, volumeKg: 5000 }])
  fake.on('GET /api/foods', () => [rice, skyr])
  fake.on('GET /api/notifications/unread', () => ({ count: 0 }))
})

describe('the quick actions', () => {
  it('opens a panel of everything one can add, each leading to its form', async () => {
    const wrapper = await mountSuspended(QuickActions, { route: '/', attachTo: document.body })
    const trigger = wrapper.get('button[aria-label="Ajouter"]')
    expect(trigger.attributes('aria-haspopup')).toBe('dialog')

    await trigger.trigger('click')
    await settle()

    expect(wrapper.findAll('dialog li').map(item => item.text())).toEqual(['Pesée', 'Aliment', 'Repas (favori)', 'Recette', 'Entraînement', 'Mensuration', 'Photo'])
    expect(wrapper.findAll('dialog a').map(link => link.attributes('href'))).toEqual([
      '/progression?ajouter=pesee', '/nutrition?ajouter=aliment', '/nutrition/favoris', '/nutrition/recettes/nouvelle',
      '/progression/mensurations?ajouter=mensurations', '/progression/photos?ajouter=photo',
    ])
    // Choosing closes the panel.
    await wrapper.get('dialog a').trigger('click')
    await settle()
    expect(wrapper.find('dialog[open] li').exists()).toBe(false)
  })

  it('starts the next workout of the program followed, or leads to training without one', async () => {
    fake.on('GET /api/training/next-workout', () => nextWorkout)
    fake.on('GET /api/workouts/preview', () => ({
      programId: nextWorkout.programId,
      programDay: { programId: nextWorkout.programId, programName: 'PPL', versionNumber: 2, dayIndex: 1, dayName: 'Pull' },
      exercises: [workoutExercise(0, 0, { name: 'Tractions' })],
    }))
    fake.on('PUT /api/workouts/{id}', () => ({}))
    const wrapper = await mountSuspended(QuickActions, { route: '/', attachTo: document.body })
    await wrapper.get('button[aria-label="Ajouter"]').trigger('click')
    await settle()
    await wrapper.findAll('dialog button').find(item => item.text() === 'Entraînement')!.trigger('click')
    await settle()
    expect(fake.calls.find(call => call.route === 'GET /api/workouts/preview')?.params).toMatchObject({ query: { day: 1 } })
    expect(navigate).toHaveBeenCalledWith('/training/seance')
    wrapper.unmount()

    await forgetWorkouts()
    navigate.mockReset()
    fake.on('GET /api/training/next-workout', () => {
      throw apiError(404, 'training.no_active_program')
    })
    const without = await mountSuspended(QuickActions, { route: '/', attachTo: document.body })
    await without.get('button[aria-label="Ajouter"]').trigger('click')
    await settle()
    await without.findAll('dialog button').find(item => item.text() === 'Entraînement')!.trigger('click')
    await settle()
    expect(navigate).toHaveBeenCalledWith('/training')
    without.unmount()
  })

  it('steps aside while the page scrolls down, and comes back when it scrolls up or ends', async () => {
    const wrapper = await mountSuspended(QuickActions, { route: '/', attachTo: document.body })
    const tucked = () => wrapper.get('button[aria-label="Ajouter"]').classes().includes('max-md:not-focus:invisible')
    const PAGE_HEIGHT = 3000
    Object.defineProperty(document.documentElement, 'scrollHeight', { configurable: true, get: () => PAGE_HEIGHT })
    const scrollTo = async (y: number) => {
      Object.defineProperty(window, 'scrollY', { configurable: true, value: y })
      window.dispatchEvent(new Event('scroll'))
      await nextTick()
    }

    try {
      await scrollTo(60)
      expect(tucked(), 'near the top it stays').toBe(false)
      await scrollTo(400)
      expect(tucked()).toBe(true)
      await scrollTo(396)
      expect(tucked(), 'a finger resting changes nothing').toBe(true)
      await scrollTo(300)
      expect(tucked()).toBe(false)
      await scrollTo(PAGE_HEIGHT - window.innerHeight)
      expect(tucked(), 'the end of the page keeps it clear').toBe(false)
    }
    finally {
      wrapper.unmount()
      Reflect.deleteProperty(document.documentElement, 'scrollHeight')
      Reflect.deleteProperty(window, 'scrollY')
    }
  })
})

describe('the notification bell', () => {
  it('says how many are unread, in words and with a badge', async () => {
    fake.on('GET /api/notifications/unread', () => ({ count: 3 }))
    const wrapper = await mountSuspended(NotificationBell, { route: '/' })
    await settle()

    expect(wrapper.get('a').attributes('href')).toBe('/notifications')
    expect(wrapper.get('a').attributes('aria-label')).toBe('Notifications, 3 non lues')
    expect(wrapper.get('[data-testid="unread-badge"]').text()).toBe('3')
  })

  it('caps the badge, and keeps quiet when nothing is unread or the count cannot be read', async () => {
    fake.on('GET /api/notifications/unread', () => ({ count: 12 }))
    const many = await mountSuspended(NotificationBell, { route: '/' })
    await settle()
    expect(many.get('[data-testid="unread-badge"]').text()).toBe('9+')
    many.unmount()

    useState('notifications-unread').value = 0
    fake.on('GET /api/notifications/unread', () => {
      throw apiError(503, null)
    })
    const failing = await mountSuspended(NotificationBell, { route: '/' })
    await settle()
    expect(failing.find('[data-testid="unread-badge"]').exists()).toBe(false)
    expect(failing.get('a').attributes('aria-label')).toBe('Notifications')
  })
})

describe('the search palette', () => {
  async function openPalette() {
    const wrapper = await mountSuspended(SearchPalette, { props: { 'open': true, 'onUpdate:open': () => {} }, attachTo: document.body })
    await settle()
    return wrapper
  }
  const options = (wrapper: Awaited<ReturnType<typeof openPalette>>) => wrapper.findAll('[role="option"]').map(option => option.text())
  async function type(wrapper: Awaited<ReturnType<typeof openPalette>>, text: string): Promise<void> {
    await wrapper.get('input').setValue(text)
    await settle()
  }

  it('is a combobox that takes the focus and offers shortcuts before anything is typed', async () => {
    const wrapper = await openPalette()

    const input = wrapper.get('input')
    expect(input.attributes('role')).toBe('combobox')
    expect(document.activeElement).toBe(input.element)
    expect(options(wrapper)).toEqual(['Ajouter une pesée', 'Ajouter un aliment', 'Démarrer la séance', 'Journal du jour', 'Coach'])
    // The lists are read again at each opening (a write elsewhere may have changed them).
    expect(fake.calls.filter(call => call.route === 'GET /api/recipes').length).toBeGreaterThanOrEqual(1)
  })

  it('finds pages, recipes, exercises, programs and sessions on the device, accents and case ignored', async () => {
    const wrapper = await openPalette()

    await type(wrapper, 'PESEES')
    expect(options(wrapper)).toContain('Toutes les pesées')
    await type(wrapper, 'pull')
    expect(options(wrapper).some(option => option.startsWith('Pull'))).toBe(true)
    await type(wrapper, bench.name.slice(0, 6))
    expect(options(wrapper).some(option => option.startsWith(bench.name))).toBe(true)
    await type(wrapper, ppl.name)
    expect(wrapper.findAll('[role="group"] > p').map(p => p.text())).toContain('Programmes')
    expect(wrapper.get('[role="status"]').text()).toMatch(/résultat/)
  })

  it('asks the API for foods once the typing pauses, and leads an editable one to its page', async () => {
    vi.useFakeTimers()
    const wrapper = await openPalette()

    // Typed fast: one question, for the last query.
    for (const text of ['r', 'ri', 'riz']) {
      await wrapper.get('input').setValue(text)
      await vi.advanceTimersByTimeAsync(50)
    }
    expect(fake.calls.some(call => call.route === 'GET /api/foods')).toBe(false)
    await vi.advanceTimersByTimeAsync(FOOD_SEARCH_DELAY_MS)
    await settle()

    const asked = fake.calls.filter(call => call.route === 'GET /api/foods')
    expect(asked).toHaveLength(1)
    expect(asked[0]?.params).toMatchObject({ query: { q: 'riz' } })
    expect(options(wrapper)).toContain(rice.name)
    const skyrIndex = wrapper.findAll('[role="option"]').findIndex(option => option.text().startsWith(skyr.name))
    await wrapper.findAll('[role="option"]')[skyrIndex]!.trigger('click')
    expect(navigate).toHaveBeenCalledWith(`/nutrition/aliments/${skyr.id}`)
  })

  it('drops the foods of an older query that answer late', async () => {
    vi.useFakeTimers()
    let answer: (foods: unknown) => void = () => {}
    fake.on('GET /api/foods', () => new Promise((resolve) => {
      answer = resolve
    }))
    const wrapper = await openPalette()
    await type(wrapper, 'pou')
    await vi.advanceTimersByTimeAsync(FOOD_SEARCH_DELAY_MS)
    // Back under the length that searches, then the old answer arrives.
    await type(wrapper, 'po')
    answer([rice])
    await settle()

    expect(options(wrapper)).not.toContain(rice.name)
  })

  it('moves through the results with the arrows (wrapping around) and opens the active one with Entrée', async () => {
    const wrapper = await openPalette()
    await type(wrapper, 'coach')

    const input = wrapper.get('input')
    const first = input.attributes('aria-activedescendant')
    await input.trigger('keydown', { key: 'ArrowUp' })
    const last = input.attributes('aria-activedescendant')
    expect(last).toBe(wrapper.findAll('[role="option"]').at(-1)!.attributes('id'))
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(input.attributes('aria-activedescendant')).toBe(first)
    await input.trigger('keydown', { key: 'ArrowDown' })
    const second = input.attributes('aria-activedescendant')
    expect(second).not.toBe(first)
    expect(wrapper.get(`#${second}`).attributes('aria-selected')).toBe('true')

    await input.trigger('keydown', { key: 'Enter' })
    await settle()
    expect(navigate).toHaveBeenCalledWith('/coach/historique')
  })

  it('keeps the result moved to when more arrive', async () => {
    vi.useFakeTimers()
    const wrapper = await openPalette()
    await type(wrapper, 'riz')
    const input = wrapper.get('input')
    await input.trigger('keydown', { key: 'ArrowDown' })
    const moved = wrapper.get(`#${input.attributes('aria-activedescendant')}`).text()
    // The foods arrive: the one moved to stays active.
    await vi.advanceTimersByTimeAsync(FOOD_SEARCH_DELAY_MS)
    await settle()
    expect(wrapper.get(`#${input.attributes('aria-activedescendant')}`).text()).toBe(moved)
  })

  it('says when nothing is found', async () => {
    fake.on('GET /api/foods', () => [])
    const wrapper = await openPalette()
    await type(wrapper, 'zzzz')
    await new Promise(resolve => setTimeout(resolve, FOOD_SEARCH_DELAY_MS + 50))
    await settle()

    expect(wrapper.text()).toContain('Rien trouvé pour «\u00A0zzzz\u00A0».')
    // Only options belong in the listbox.
    expect(wrapper.get('[role="listbox"]').text()).toBe('')
    expect(wrapper.get('[role="status"]').text()).toBe('Aucun résultat')
  })
})

describe('the page header', () => {
  it('opens the search with Ctrl+K, and has the search, add and notification buttons', async () => {
    const wrapper = await mountSuspended(DefaultLayout, { route: '/', attachTo: document.body })
    await settle()

    expect(wrapper.find('header button[aria-label="Rechercher"]').exists()).toBe(true)
    expect(wrapper.find('header button[aria-label="Ajouter"]').exists()).toBe(true)
    expect(wrapper.find('header a[href="/notifications"]').exists()).toBe(true)
    expect(wrapper.find('input[role="combobox"]').exists()).toBe(false)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true }))
    await settle()
    await settle()
    expect(wrapper.find('input[role="combobox"]').exists()).toBe(true)
  })

  it('opens the search with ⌘K too, and shows the shortcut on larger screens', async () => {
    const wrapper = await mountSuspended(DefaultLayout, { route: '/', attachTo: document.body })
    await settle()
    expect(wrapper.findAll('header button').some(item => /Rechercher… (Ctrl K|⌘K)/.test(item.text()))).toBe(true)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'K', metaKey: true }))
    await settle()
    await settle()
    expect(wrapper.find('input[role="combobox"]').exists()).toBe(true)
  })
})

describe('the forms the quick actions and the search lead to', () => {
  it('opens the adding panel of the journal, searching for the food found, then cleans the URL', async () => {
    fake.on('GET /api/nutrition/days/{day}', () => day(localDay()))
    fake.on('GET /api/foods/recent', () => [])
    fake.on('GET /api/nutrition/favorite-meals', () => [])
    const wrapper = await mountSuspended(NutritionPage, { route: '/nutrition?ajouter=aliment&recherche=Riz', attachTo: document.body })
    await settle()

    expect(wrapper.findAll('dialog input').map(input => (input.element as HTMLInputElement).value)).toContain('Riz')
    expect(navigate).toHaveBeenCalledWith({ query: {} }, { replace: true })
    // The search really runs, and the foods show.
    await new Promise(resolve => setTimeout(resolve, 300))
    await settle()
    expect(fake.calls.find(call => call.route === 'GET /api/foods')?.params).toMatchObject({ query: { q: 'Riz' } })
    expect(wrapper.find('dialog').text()).toContain(rice.name)
  })

  it('puts the focus on the weight to weigh in', async () => {
    fake.on('GET /api/progression/weight', () => summary())
    fake.on('GET /api/progression/weight-goal', () => {
      throw apiError(404, null)
    })
    const wrapper = await mountSuspended(ProgressionPage, { route: '/progression?ajouter=pesee', attachTo: document.body })
    await settle()

    expect(document.activeElement?.getAttribute('inputmode')).toBe('decimal')
    expect(navigate).toHaveBeenCalledWith({ query: {} }, { replace: true })
    wrapper.unmount()
  })

  it('puts the focus on the first measure to measure, once the page has its data', async () => {
    fake.on('GET /api/progression/measures', () => measures)
    fake.on('GET /api/progression/measurements', () => ({ period: '3m', measures: [] }))
    fake.on('GET /api/progression/measurements/{day}', () => {
      throw apiError(404, 'progression.measurements_not_found')
    })
    const wrapper = await mountSuspended(MeasurementsPage, { route: '/progression/mensurations?ajouter=mensurations', attachTo: document.body })
    await settle()

    expect(document.activeElement).toBe(field(wrapper, 'Cou').element)
    expect(navigate).toHaveBeenCalledWith({ query: {} }, { replace: true })
    wrapper.unmount()
  })

  it('puts the focus on the file picker to add a photo', async () => {
    fake.on('GET /api/progression/photo-sessions', () => [])
    const wrapper = await mountSuspended(PhotosPage, { route: '/progression/photos?ajouter=photo', attachTo: document.body })
    await settle()

    expect(document.activeElement?.getAttribute('type')).toBe('file')
    expect(navigate).toHaveBeenCalledWith({ query: {} }, { replace: true })
    wrapper.unmount()
  })
})
