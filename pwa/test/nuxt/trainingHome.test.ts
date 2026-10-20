import 'fake-indexeddb/auto'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PAGE_HEADING_KEY } from '~/composables/usePageHeading'
import { resetWorkoutOutbox, useWorkoutOutbox } from '~/features/training/outbox'
import { resetWorkoutSession, useWorkoutSession } from '~/features/training/session'
import type { Workout, WorkoutPlan } from '~/features/training/workout'
import TrainingPage from '~/pages/training/index.vue'
import WorkoutDetailPage from '~/pages/training/seances/[id].vue'
import HistoryPage from '~/pages/training/seances/index.vue'
import { ApiError } from '~/services/api/errors'
import { claimDevice } from '~/services/offline/owner'
import { offlineStore } from '~/services/offline/store'
import { button } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'
import { ppl, workoutExercise } from '../support/training'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake

const offline = () => new ApiError('network', null, 'x', 'offline')
function planOf(day: number): WorkoutPlan {
  const name = ppl.days[day]!.name
  return {
    programId: ppl.id,
    programDay: { programId: ppl.id, programName: ppl.name, versionNumber: ppl.currentVersion, dayIndex: day, dayName: name },
    exercises: day === 0 ? [workoutExercise(0, 0, { name: 'Développé couché' }), workoutExercise(1, 1, { name: 'Élévations' })] : [workoutExercise(0, 0, { name: 'Rowing Yates' })],
  }
}
const elsewhere: Workout = { id: 'w-elsewhere', status: 'in_progress', programDay: planOf(0).programDay, startedAt: new Date().toISOString(), finishedAt: null, exercises: planOf(0).exercises, sets: [] }

async function settle(): Promise<void> {
  for (let i = 0; i < 5; i++) {
    await flushPromises()
    await new Promise(resolve => setTimeout(resolve, 0))
  }
}

async function openHome() {
  const wrapper = await mountSuspended(TrainingPage, { route: '/training' })
  await settle()
  return wrapper
}

beforeEach(async () => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  resetWorkoutOutbox()
  resetWorkoutSession()
  await (await offlineStore()).clear()
  await claimDevice('alex')
  fake.on('GET /api/programs', () => [ppl])
  fake.on('GET /api/workouts/preview', (_, params) => planOf(Number((params as { query?: { day?: number } }).query?.day)))
  fake.on('GET /api/workouts/in-progress', () => [])
})

describe('training home', () => {
  it('starts a day of the program followed', async () => {
    const wrapper = await openHome()
    expect(wrapper.text()).toContain('Démarrer Push')
    expect(wrapper.text()).toContain('3\u00A0exercices')
    expect(fake.calls.filter(call => call.route === 'GET /api/workouts/preview'), 'every day kept ahead of time').toHaveLength(2)

    await button(wrapper, 'Démarrer Pull').trigger('click')
    await settle()
    expect(navigate).toHaveBeenCalledWith('/training/seance')
    expect(useWorkoutSession().workout.value?.exercises.map(exercise => exercise.name)).toEqual(['Rowing Yates'])
  })

  it('starts a day even opened without signal, from what the device kept', async () => {
    await openHome()
    clearNuxtData()
    fake.on('GET /api/programs', () => {
      throw offline()
    })
    fake.on('GET /api/workouts/preview', () => {
      throw offline()
    })
    fake.on('GET /api/workouts/in-progress', () => {
      throw offline()
    })

    const wrapper = await openHome()
    expect(wrapper.text()).toContain('Hors ligne\u00A0: le programme tel que gardé sur cet appareil.')
    await button(wrapper, 'Démarrer Pull').trigger('click')
    await settle()

    expect(navigate).toHaveBeenCalledWith('/training/seance')
    expect(useWorkoutSession().workout.value?.programDay?.dayName).toBe('Pull')
  })

  it('says when a day cannot start offline, and still offers a free workout', async () => {
    fake.on('GET /api/workouts/preview', () => {
      throw offline()
    })
    const wrapper = await openHome()

    await button(wrapper, 'Démarrer Push').trigger('click')
    await settle()
    expect(wrapper.text()).toContain('Pas de connexion, et ce jour n’est pas encore gardé sur l’appareil.')

    await button(wrapper, 'Séance libre').trigger('click')
    await settle()
    expect(useWorkoutSession().workout.value?.programDay).toBeNull()
  })

  it('offers to resume a workout in progress, or to finish it through the confirmation', async () => {
    fake.on('GET /api/workouts/in-progress', () => [elsewhere])
    const wrapper = await openHome()

    expect(wrapper.text()).toContain('Ta séance est toujours en cours (Push).')
    expect(wrapper.findAll('button').some(b => b.text().startsWith('Démarrer')), 'one workout at a time').toBe(false)
    await button(wrapper, 'Terminer').trigger('click')
    await settle()
    expect(useWorkoutSession().workout.value?.id).toBe('w-elsewhere')
    expect(navigate).toHaveBeenCalledWith({ path: '/training/seance', query: { terminer: '1' } })

    await button(wrapper, 'Reprendre').trigger('click')
    await settle()
    expect(navigate).toHaveBeenLastCalledWith('/training/seance')
  })

  it('does not offer a workout the device has just finished', async () => {
    fake.on('GET /api/workouts/in-progress', () => [elsewhere])
    fake.on('POST /api/workouts/{id}/finish', () => {
      throw offline()
    })
    await useWorkoutOutbox().queue({ op: 'finish', workoutId: 'w-elsewhere', finishedAt: new Date().toISOString() })

    const wrapper = await openHome()
    expect(wrapper.text()).not.toContain('toujours en cours')
  })

  it('leads to a first program, or to choosing one', async () => {
    fake.on('GET /api/programs', () => [])
    const empty = await openHome()
    expect(empty.text()).toContain('Aucun programme suivi')
    expect(empty.findAll('a').find(a => a.text() === 'Créer un programme')?.attributes('href')).toBe('/training/programmes/nouveau')

    clearNuxtData()
    fake.on('GET /api/programs', () => [{ ...ppl, active: false }])
    const none = await openHome()
    expect(none.findAll('a').find(a => a.text() === 'Mes programmes')?.attributes('href')).toBe('/training/programmes')
  })

  it('offers a retry when programs cannot load and nothing was kept', async () => {
    fake.on('GET /api/programs', () => {
      throw apiError(503, null)
    })
    const wrapper = await openHome()

    expect(wrapper.text()).toContain('Réessayer')
  })
})

describe('history', () => {
  it('lists finished workouts and shows one with its records', async () => {
    fake.on('GET /api/workouts', () => [{ id: 'w1', dayName: 'Push', startedAt: '2026-09-28T18:00:00Z', finishedAt: '2026-09-28T19:05:09Z', exercises: 3, workingSets: 9, volumeKg: 5400.4 }])
    const list = await mountSuspended(HistoryPage, { route: '/training/seances' })
    await settle()
    expect(list.text()).toContain('Push · lundi 28 septembre')
    expect(list.text()).toContain('1:05:09 · 3 exercices · 9 séries · 5\u202F400\u00A0kg soulevés')

    const plan = planOf(0)
    fake.on('GET /api/workouts/{id}', () => ({ id: 'w1', status: 'finished', programDay: plan.programDay, startedAt: '2026-09-28T18:00:00Z', finishedAt: '2026-09-28T19:00:00Z', exercises: [{ ...plan.exercises[0]!, newRecords: [{ kind: 'estimated_one_rep_max', value: 110, loadKg: null, date: '2026-09-28T18:00:00Z', workoutId: 'w1' }] }, plan.exercises[1]!], sets: [{ id: 's1', exerciseIndex: 0, kind: 'effective', loadKg: 85, reps: 7, seconds: null, rir: 0, doneAt: '2026-09-28T18:10:00Z' }] }))
    const detail = await mountSuspended(WorkoutDetailPage, { route: '/training/seances/w1' })
    await settle()
    expect(detail.text()).toContain('Records battus')
    expect(detail.text()).toContain('Développé couché\u00A0— 1RM estimé\u00A0: 110\u00A0kg')
    expect(detail.text()).toContain('Effective 85\u00A0kg × 7 · RIR\u00A00')
    expect(detail.text()).toContain('Pas fait.')
    expect(detail.findAll('li').map(item => item.text()).find(text => text.includes('85\u00A0kg')), 'each set says its number').toMatch(/^Série 1/)
    expect(useState(PAGE_HEADING_KEY).value, 'the day names the page').toBe(plan.programDay!.dayName)
  })

  it('says a workout finished offline is on its way', async () => {
    fake.on('GET /api/workouts/{id}', () => {
      throw offline()
    })
    fake.on('POST /api/workouts/{id}/finish', () => {
      throw offline()
    })
    await useWorkoutOutbox().queue({ op: 'finish', workoutId: 'w2', finishedAt: new Date().toISOString() })
    const detail = await mountSuspended(WorkoutDetailPage, { route: '/training/seances/w2' })
    await settle()

    expect(detail.text()).toContain('Séance terminée\u00A0! Elle est gardée sur cet appareil')
  })

  it('offers a retry for another workout the device cannot load', async () => {
    fake.on('GET /api/workouts/{id}', () => {
      throw offline()
    })
    const detail = await mountSuspended(WorkoutDetailPage, { route: '/training/seances/w3' })
    await settle()

    expect(detail.text()).toContain('Réessayer')
  })
})
