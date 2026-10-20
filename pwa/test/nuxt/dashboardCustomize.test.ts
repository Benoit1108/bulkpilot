import 'fake-indexeddb/auto'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardPage from '~/pages/index.vue'
import WorkoutWidget from '~/features/dashboard/components/WorkoutWidget.vue'
import { resetWorkoutSession } from '~/features/training/session'
import { messageForCode } from '~/services/api/messages'
import { localDay } from '~/services/calendar/days'
import { button } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { analytics, layout, nextWorkout } from '../support/dashboard'
import { workoutExercise } from '../support/training'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake
const today = localDay()

async function settle(): Promise<void> {
  for (let i = 0; i < 5; i++) {
    await flushPromises()
    await new Promise(resolve => setTimeout(resolve, 0))
  }
}

beforeEach(async () => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  resetWorkoutSession()
})

describe('personalising the dashboard', () => {
  async function customize() {
    // Only the layout matters here: every widget is hidden but the weight.
    fake.on('GET /api/me/dashboard', () => layout(['nutrition', 'meals', 'workout', 'coach', 'goal', 'records', 'report']))
    fake.on('GET /api/progression/weight', () => {
      throw apiError(503, null)
    })
    fake.on('PUT /api/me/dashboard', body => body)
    const wrapper = await mountSuspended(DashboardPage, { route: '/', attachTo: document.body })
    await settle()
    await button(wrapper, 'Personnaliser le tableau de bord').trigger('click')
    return wrapper
  }

  it('shows, hides and moves widgets with labelled buttons, then saves the whole order', async () => {
    const wrapper = await customize()

    // Entering the mode: its title takes the focus (the button that opened it is gone).
    expect(document.activeElement?.textContent?.trim()).toBe('Personnaliser le tableau de bord')
    const rows = () => wrapper.findAll('[data-widget]').map(row => row.attributes('data-widget'))
    expect(rows()).toEqual(['weight', 'nutrition', 'meals', 'workout', 'coach', 'goal', 'records', 'report'])
    expect(button(wrapper, 'Monter le widget Poids').attributes('disabled')).toBeDefined()

    await button(wrapper, 'Monter le widget Coach').trigger('click')
    await settle()
    expect(rows().slice(3, 5)).toEqual(['coach', 'workout'])
    expect(wrapper.get('[role="status"]').text()).toBe('Coach en position 4 sur 8.')
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Monter le widget Coach')

    await wrapper.get('[data-widget="coach"] input[type="checkbox"]').setValue(true)
    await wrapper.get('[data-widget="weight"] input[type="checkbox"]').setValue(false)
    await wrapper.get('form').trigger('submit')
    await settle()

    const put = fake.calls.find(call => call.route === 'PUT /api/me/dashboard')
    expect(put?.body).toEqual({ widgets: [
      { key: 'weight', visible: false }, { key: 'nutrition', visible: false }, { key: 'meals', visible: false }, { key: 'coach', visible: true },
      { key: 'workout', visible: false }, { key: 'goal', visible: false }, { key: 'records', visible: false }, { key: 'report', visible: false },
    ] })
    expect(wrapper.find('[data-widget]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="status"]').text()).toBe('Tableau de bord enregistré.')
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Personnaliser le tableau de bord')
  })

  it('sends back as they were the widgets of a newer server, without offering them', async () => {
    fake.on('GET /api/me/dashboard', () => ({ widgets: [{ key: 'sleep' as never, visible: true }, ...layout().widgets] }))
    fake.on('GET /api/progression/weight', () => {
      throw apiError(503, null)
    })
    fake.on('PUT /api/me/dashboard', body => body)
    const wrapper = await mountSuspended(DashboardPage, { route: '/', attachTo: document.body })
    await settle()
    await button(wrapper, 'Personnaliser le tableau de bord').trigger('click')

    expect(wrapper.find('[data-widget="sleep"]').exists()).toBe(false)
    await wrapper.get('form').trigger('submit')
    await settle()
    const put = fake.calls.find(call => call.route === 'PUT /api/me/dashboard')?.body as { widgets: Array<{ key: string }> }
    expect(put.widgets.map(widget => widget.key)).toEqual([...layout().widgets.map(widget => widget.key), 'sleep'])
  })

  it('moves the focus to the other arrow once a widget reaches the top', async () => {
    const wrapper = await customize()

    await button(wrapper, 'Monter le widget Nutrition').trigger('click')
    await settle()
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Descendre le widget Nutrition')
  })

  it('leaves the layout as it was on cancel, and shows a refused layout\'s error', async () => {
    const wrapper = await customize()
    await button(wrapper, 'Annuler').trigger('click')
    expect(fake.calls.some(call => call.route === 'PUT /api/me/dashboard')).toBe(false)

    await button(wrapper, 'Personnaliser le tableau de bord').trigger('click')
    fake.on('PUT /api/me/dashboard', () => {
      throw apiError(422, 'identity.invalid_dashboard_layout')
    })
    await wrapper.get('form').trigger('submit')
    await settle()
    expect(wrapper.get('form [role="alert"]').text()).toBe(messageForCode('identity.invalid_dashboard_layout'))
    expect(wrapper.find('[data-widget]').exists()).toBe(true)
  })
})

describe('the workout widget', () => {
  it('starts the next day of the program, as the server names it', async () => {
    fake.on('GET /api/training/next-workout', () => nextWorkout)
    fake.on('GET /api/training/analytics', () => analytics(1, 0))
    fake.on('GET /api/workouts/preview', () => ({
      programId: nextWorkout.programId,
      programDay: { programId: nextWorkout.programId, programName: 'PPL', versionNumber: 2, dayIndex: 1, dayName: 'Pull' },
      exercises: [workoutExercise(0, 0, { name: 'Tractions' })],
    }))
    fake.on('PUT /api/workouts/{id}', () => ({}))
    const wrapper = await mountSuspended(WorkoutWidget, { props: { view: 'day', today } })
    await settle()

    await button(wrapper, 'Démarrer Pull').trigger('click')
    await settle()

    expect(fake.calls.find(call => call.route === 'GET /api/workouts/preview')?.params).toMatchObject({ query: { programId: nextWorkout.programId, day: 1 } })
    expect(navigate).toHaveBeenCalledWith('/training/seance')
    // The day reads only the next workout.
    expect(fake.calls.some(call => call.route === 'GET /api/training/analytics')).toBe(false)
  })

  it('offers to resume a workout in progress instead', async () => {
    fake.on('GET /api/training/next-workout', () => ({ ...nextWorkout, inProgress: true }))
    fake.on('GET /api/training/analytics', () => analytics(1, 0))
    const wrapper = await mountSuspended(WorkoutWidget, { props: { view: 'day', today } })
    await settle()

    expect(wrapper.get('h2 a').text(), 'the whole tile leads to the section').toBe('Entraînement')
    expect(wrapper.get('h2 a').attributes('href')).toBe('/training')
    expect(wrapper.findAll('a').map(a => a.text())).toContain('Reprendre la séance')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('counts the sessions of the week in the week view', async () => {
    fake.on('GET /api/training/next-workout', () => nextWorkout)
    fake.on('GET /api/training/analytics', () => analytics(3, 1))
    const wrapper = await mountSuspended(WorkoutWidget, { props: { view: 'week', today } })
    await settle()

    expect(wrapper.text()).toContain('3 séances ces 7 derniers jours')
    expect(wrapper.text()).toContain('3 par semaine en moyenne')
    expect(fake.calls.some(call => call.route === 'GET /api/training/next-workout')).toBe(false)
  })
})
