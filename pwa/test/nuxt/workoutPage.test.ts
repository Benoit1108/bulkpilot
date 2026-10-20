import 'fake-indexeddb/auto'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import RestTimer from '~/features/training/components/RestTimer.vue'
import SetForm from '~/features/training/components/SetForm.vue'
import WorkoutClock from '~/features/training/components/WorkoutClock.vue'
import { resetWorkoutOutbox, type WorkoutWrite } from '~/features/training/outbox'
import { resetWorkoutSession, useWorkoutSession } from '~/features/training/session'
import type { WorkoutPlan } from '~/features/training/workout'
import WorkoutPage from '~/pages/training/seance.vue'
import { claimDevice } from '~/services/offline/owner'
import { offlineStore } from '~/services/offline/store'
import { button, choice, field } from '../support/dom'
import { createFakeApi } from '../support/fakeApi'
import { ppl, workoutExercise } from '../support/training'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake

const plan: WorkoutPlan = {
  programId: ppl.id,
  programDay: { programId: ppl.id, programName: 'PPL Masse', versionNumber: 2, dayIndex: 0, dayName: 'Push' },
  exercises: [
    workoutExercise(0, 0, {
      name: 'Développé couché',
      note: 'Omoplates serrées',
      programNotes: 'Pause sur la poitrine',
      warmupSets: 1,
      dropSet: true,
      previous: { date: '2026-09-21T18:00:00Z', sets: [
        { id: 'p0', exerciseIndex: 0, kind: 'warmup', loadKg: 40, reps: 10, seconds: null, rir: null, doneAt: '2026-09-21T18:01:00Z' },
        { id: 'p1', exerciseIndex: 0, kind: 'normal', loadKg: 80, reps: 12, seconds: null, rir: 2, doneAt: '2026-09-21T18:05:00Z' },
      ] },
      suggestion: { reason: 'increase_load', loadKg: 82.5, reps: 8, seconds: null },
    }),
    workoutExercise(1, 1, { name: 'Élévations latérales', sets: 1, restSeconds: 45 }),
    workoutExercise(2, 1, { name: 'Gainage', measurement: 'duration', repMin: 30, repMax: 60, sets: 1, restSeconds: 60 }),
  ],
}

/** IndexedDB (fake-indexeddb) answers on later macrotasks: promises alone do not let it finish. */
async function settle(): Promise<void> {
  for (let i = 0; i < 5; i++) {
    await flushPromises()
    await new Promise(resolve => setTimeout(resolve, 0))
  }
}

async function queued(): Promise<WorkoutWrite[]> {
  return (await (await offlineStore()).queue<WorkoutWrite>()).map(item => item.write)
}

async function openWorkout(query = '') {
  const wrapper = await mountSuspended(WorkoutPage, { route: `/training/seance${query}`, attachTo: document.body })
  await vi.waitFor(() => expect(wrapper.find('h1').exists()).toBe(true))
  await settle()
  return wrapper
}

beforeEach(async () => {
  fake.reset()
  navigate.mockReset()
  resetWorkoutOutbox()
  resetWorkoutSession()
  await (await offlineStore()).clear()
  await claimDevice('alex')
})

afterEach(() => {
  vi.useRealTimers()
})

describe('the workout session', () => {
  it('starts from a plan, goes through a superset and rests after the round', async () => {
    const session = useWorkoutSession()
    await session.start(plan, new Date('2026-09-28T18:00:00Z'))
    await session.goTo(1)
    await session.recordSet({ kind: 'normal', loadKg: 10, reps: 15, seconds: null, rir: null }, new Date('2026-09-28T18:10:00Z'))
    expect([session.workout.value?.current, session.workout.value?.rest], 'straight on to A2').toEqual([2, null])
    await session.recordSet({ kind: 'normal', loadKg: null, reps: null, seconds: 45, rir: null }, new Date('2026-09-28T18:11:00Z'))
    expect(session.workout.value?.rest).toEqual({ totalMs: 60_000, endsAt: new Date('2026-09-28T18:12:00Z').getTime(), remainingMs: null })

    const writes = await queued()
    expect(writes[0]).toMatchObject({ op: 'start', body: { startedAt: '2026-09-28T18:00:00.000Z', programId: ppl.id, versionNumber: 2, dayIndex: 0 } })
    expect(writes.filter(write => write.op === 'record_set')).toHaveLength(2)

    resetWorkoutSession()
    const reopened = useWorkoutSession()
    await reopened.load()
    expect(reopened.workout.value?.sets, 'kept on the device').toHaveLength(2)
    expect(reopened.workout.value?.rest, 'a rest that ended while the app was closed is over').toBeNull()
  })

  it('does not bring back an older copy while a change is under way', async () => {
    const session = useWorkoutSession()
    const loading = session.load()
    await session.start(plan)
    await loading
    expect(session.workout.value?.programDay?.dayName).toBe('Push')
  })

  it('pauses, changes and ends a rest', async () => {
    const session = useWorkoutSession()
    await session.start(plan)
    await session.recordSet({ kind: 'warmup', loadKg: 40, reps: 10, seconds: null, rir: null }, new Date(1_000_000))
    await session.pauseRest(1_030_000)
    expect(session.workout.value?.rest).toMatchObject({ endsAt: null, remainingMs: 60_000 })
    await session.adjustRest(15)
    expect(session.workout.value?.rest).toMatchObject({ remainingMs: 75_000 })
    await session.resumeRest(2_000_000)
    expect(session.workout.value?.rest).toMatchObject({ endsAt: 2_075_000, remainingMs: null })
    await session.adjustRest(-100, 2_000_000)
    expect(session.workout.value?.rest, 'brought to zero, it is over').toBeNull()
  })

  it('corrects, removes, adds on the go, then finishes in one go', async () => {
    const session = useWorkoutSession()
    await session.start(null)
    await session.addExercise({ id: 'e9', source: 'custom', name: 'Rowing Yates', primaryMuscle: 'upper_back', secondaryMuscles: [], type: 'compound', equipment: 'barbell', measurement: 'weight', description: null, mediaUrl: null, repMin: 6, repMax: 10, targetRir: 2, restSeconds: 150, tempo: null, note: 'Buste à 30°', editable: true })
    expect(session.workout.value?.exercises[0]).toMatchObject({ sets: 3, incrementKg: 2.5, block: 0, added: true, note: 'Buste à 30°' })
    const set = await session.recordSet({ kind: 'normal', loadKg: 70, reps: 8, seconds: null, rir: null })
    await session.correctSet(set!.id, { kind: 'effective', loadKg: 72.5, reps: 8, seconds: null, rir: 0 })
    expect(session.workout.value?.sets[0]).toMatchObject({ kind: 'effective', loadKg: 72.5 })
    await session.removeSet(set!.id)

    const id = await session.finish(new Date('2026-09-28T19:00:00Z'))
    expect(session.workout.value).toBeNull()
    expect((await queued()).map(write => write.op)).toEqual(['start', 'add_exercise', 'record_set', 'record_set', 'remove_set', 'finish'])
    expect(await (await offlineStore()).get('training.workout'), 'kept with its last write').toBeNull()
    expect((await queued()).at(-1)).toMatchObject({ workoutId: id, finishedAt: '2026-09-28T19:00:00.000Z' })
  })
})

describe('the rest timer, the clock and the set form', () => {
  it('counts down, announces a few steps and the end, even one that passed', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'Date'] })
    vi.setSystemTime(10_000)
    const vibrate = vi.fn()
    vi.stubGlobal('navigator', Object.assign(Object.create(navigator), { vibrate }))
    const wrapper = await mountSuspended(RestTimer, { props: { rest: { totalMs: 90_000, endsAt: 100_000, remainingMs: null } } })
    expect(wrapper.text()).toContain('01:30')

    await vi.advanceTimersByTimeAsync(30_000)
    expect(wrapper.get('[role="status"]').text()).toBe('Repos\u00A0: encore 1\u00A0min.')
    await vi.advanceTimersByTimeAsync(60_000)
    expect(wrapper.emitted('done')).toHaveLength(1)
    expect(vibrate).toHaveBeenCalled()

    await button(wrapper, '+15\u00A0s').trigger('click')
    await button(wrapper, '−15\u00A0s').trigger('click')
    await button(wrapper, 'Pause').trigger('click')
    await button(wrapper, 'Passer').trigger('click')
    expect([wrapper.emitted('adjust'), wrapper.emitted('pause'), wrapper.emitted('skip')]).toEqual([[[15], [-15]], [[]], [[]]])

    await wrapper.setProps({ rest: { totalMs: 90_000, endsAt: null, remainingMs: 30_000 } })
    expect(wrapper.get('h2').text()).toBe('Repos en pause')
    expect(button(wrapper, 'Pause').attributes('aria-pressed')).toBe('true')
    await button(wrapper, 'Pause').trigger('click')
    expect(wrapper.emitted('resume')).toHaveLength(1)

    const over = await mountSuspended(RestTimer, { props: { rest: { totalMs: 90_000, endsAt: 5_000, remainingMs: null } } })
    expect(over.emitted('done'), 'ended while the app was closed').toHaveLength(1)
  })

  it('shows how long the workout lasts, and says once it is long', async () => {
    const wrapper = await mountSuspended(WorkoutClock, { props: { startedAt: new Date(Date.now() - 61 * 60_000).toISOString() } })

    expect(wrapper.text()).toContain('plus d’1\u00A0h')
    expect(wrapper.text()).toContain('Durée de la séance\u00A0: 1\u00A0h 1\u00A0min')
    expect(wrapper.emitted('long')).toHaveLength(1)
  })

  it('takes a set in taps, checks it, and says where the fields are wrong', async () => {
    const wrapper = await mountSuspended(SetForm, { props: { exercise: plan.exercises[0]!, initial: { loadKg: 80, reps: 8, kind: 'normal' }, submitLabel: 'Valider la série' }, attachTo: document.body })
    await button(wrapper, 'Ajouter 2,5 kg').trigger('click')
    expect(wrapper.find('[role="status"]').text()).toBe('Charge\u00A0: 82,5\u00A0kg')
    await button(wrapper, 'Une répétition de plus').trigger('click')
    await choice(wrapper, '1').trigger('click')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.emitted('submit')).toEqual([[{ kind: 'normal', loadKg: 82.5, reps: 9, seconds: null, rir: 1 }]])

    await choice(wrapper, 'Drop set').setValue(true)
    expect(wrapper.text()).toContain('Plus léger, juste après la série, sans repos.')
    await button(wrapper, 'Autre…').trigger('click')
    expect(wrapper.findAll('label').some(label => label.text() === 'Effective')).toBe(true)

    await field(wrapper, 'Charge').setValue('1200')
    await field(wrapper, 'Répétitions').setValue('')
    await wrapper.get('form').trigger('submit')
    expect(wrapper.text()).toContain('Une charge de 0 à 1\u202F000 kg.')
    expect(wrapper.text()).toContain('De 0 à 300 répétitions.')
    expect(document.activeElement).toBe(field(wrapper, 'Charge').element)
    expect(wrapper.emitted('submit')).toHaveLength(1)

    const timed = await mountSuspended(SetForm, { props: { exercise: plan.exercises[2]!, initial: { seconds: 30 }, submitLabel: 'Valider la série', correcting: true } })
    await button(timed, 'Ajouter 5 secondes').trigger('click')
    await timed.get('form').trigger('submit')
    expect(timed.emitted('submit')).toEqual([[{ kind: 'normal', loadKg: null, reps: null, seconds: 35, rir: null }]])
    await button(timed, 'Annuler').trigger('click')
    expect(timed.emitted('cancel')).toHaveLength(1)
  })
})

describe('training mode', () => {
  it('shows what to do, starting with the planned warm-up at half the load', async () => {
    await useWorkoutSession().start(plan)
    const wrapper = await openWorkout()

    expect(wrapper.get('h1').text()).toBe('Push')
    expect(wrapper.text()).toContain('Exercice 1/3')
    expect(wrapper.text()).toContain('Omoplates serrées')
    expect(wrapper.text()).toContain('Pause sur la poitrine')
    expect(wrapper.text()).toContain('Échauffement 1/1')
    expect(wrapper.get('[data-testid="previous-sets"]').text(), 'working sets only').toBe('80\u00A0kg × 12 · RIR\u00A02')
    expect(wrapper.text()).toContain('Suggestion\u00A0: 82,5\u00A0kg × 8')
    expect((field(wrapper, 'Charge').element as HTMLInputElement).value).toBe('42,5')
    expect((choice(wrapper, 'Échauffement').element as HTMLInputElement).checked).toBe(true)
  })

  it('records a set, says so, rests, and starts the first working set from the suggestion', async () => {
    const session = useWorkoutSession()
    await session.start(plan)
    const wrapper = await openWorkout()

    await wrapper.get('form').trigger('submit')
    await settle()
    expect(wrapper.text()).toContain('Série enregistrée\u00A0: 42,5\u00A0kg × 12.')
    expect(wrapper.findComponent(RestTimer).exists()).toBe(true)
    expect(document.activeElement?.textContent?.trim(), 'focus on the rest').toBe('Repos')
    expect((field(wrapper, 'Charge').element as HTMLInputElement).value, 'the suggestion, not the warm-up').toBe('82,5')
    expect(wrapper.text()).toContain('Série 1/2')

    await button(wrapper, 'Passer').trigger('click')
    await settle()
    expect(wrapper.findComponent(RestTimer).exists()).toBe(false)
    expect(document.activeElement?.textContent?.trim()).toBe('Développé couché')
  })

  it('offers the planned drop set a quarter lighter, then says all is done', async () => {
    const session = useWorkoutSession()
    await session.start({ ...plan, exercises: [{ ...plan.exercises[0]!, warmupSets: 0 }] })
    await session.recordSet({ kind: 'normal', loadKg: 80, reps: 8, seconds: null, rir: null })
    await session.recordSet({ kind: 'normal', loadKg: 80, reps: 8, seconds: null, rir: null })
    const wrapper = await openWorkout()

    expect(wrapper.text()).toContain('Drop set, sans repos')
    expect((field(wrapper, 'Charge').element as HTMLInputElement).value).toBe('60')
    expect((choice(wrapper, 'Drop set').element as HTMLInputElement).checked).toBe(true)
    await wrapper.get('form').trigger('submit')
    await settle()

    expect(wrapper.text()).toContain('Toutes les séries prévues sont faites.')
    await button(wrapper, 'Ajouter une série').trigger('click')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('corrects a set in its own form, then goes back to it', async () => {
    const session = useWorkoutSession()
    await session.start(plan)
    await session.recordSet({ kind: 'warmup', loadKg: 40, reps: 10, seconds: null, rir: null })
    await session.endRest()
    const wrapper = await openWorkout()

    await button(wrapper, 'Corriger la série 1').trigger('click')
    await settle()
    expect(wrapper.get('[data-testid="correction"] h3').text()).toBe('Correction de la série 1 (Échauffement)')
    expect(document.activeElement).toBe(field(wrapper, 'Charge').element)
    await field(wrapper, 'Répétitions').setValue('12')
    await wrapper.get('[data-testid="correction"] form').trigger('submit')
    await settle()
    expect(session.workout.value?.sets[0]?.reps).toBe(12)
    expect(document.activeElement?.textContent?.trim()).toBe('Corriger')

    await button(wrapper, 'Corriger la série 1').trigger('click')
    await button(wrapper, 'Suivant').trigger('click')
    await settle()
    expect(wrapper.find('[data-testid="correction"]').exists(), 'moving on closes the correction').toBe(false)
  })

  it('removes a set in two taps', async () => {
    const session = useWorkoutSession()
    await session.start(plan)
    await session.recordSet({ kind: 'warmup', loadKg: 40, reps: 10, seconds: null, rir: null })
    const wrapper = await openWorkout()

    await button(wrapper, 'Supprimer').trigger('click')
    expect(session.workout.value?.sets).toHaveLength(1)
    await button(wrapper, 'Confirmer').trigger('click')
    await settle()
    expect(session.workout.value?.sets).toEqual([])
    expect(wrapper.text()).toContain('Série supprimée.')
  })

  it('says where the superset goes, and moves between exercises', async () => {
    fake.on('GET /api/exercises', () => [])
    const session = useWorkoutSession()
    await session.start(plan)
    const wrapper = await openWorkout()

    await button(wrapper, 'Suivant').trigger('click')
    await settle()
    expect(wrapper.text()).toContain('Superset B · 1/2\u00A0— ensuite\u00A0: Gainage (sans repos)')
    expect(wrapper.text()).toContain('Exercice 2 sur 3\u00A0: Élévations latérales.')
    expect(document.activeElement?.textContent?.trim()).toBe('Élévations latérales')
    await button(wrapper, 'Tous les exercices').trigger('click')
    await settle()
    await wrapper.get('dialog[open]').findAll('li button')[2]!.trigger('click')
    await settle()
    expect(wrapper.text()).toContain('Superset B · 2/2\u00A0— puis repos\u00A0: 1\u00A0min')
    expect(wrapper.text()).toContain('Durée')
    await button(wrapper, 'Précédent').trigger('click')
    expect(session.workout.value?.current).toBe(1)

    await button(wrapper, 'Ajouter un exercice').trigger('click')
    await settle()
    expect(wrapper.text(), 'no way out of the workout from here').not.toContain('Créer un exercice')
  })

  it('asks before finishing with sets left, carrying on being the obvious choice', async () => {
    const session = useWorkoutSession()
    await session.start(plan)
    const wrapper = await openWorkout('?terminer=1')

    const dialog = wrapper.get('dialog[open]')
    expect(dialog.text()).toContain('Il reste des séries sur 3\u00A0exercices\u00A0: Développé couché, Élévations latérales, Gainage.')
    expect(dialog.findAll('button').filter(b => b.attributes('aria-label') !== 'Fermer')[0]!.text()).toBe('Continuer la séance')
    await button(wrapper, 'Terminer quand même').trigger('click')
    await settle()
    expect(navigate).toHaveBeenCalledWith(expect.stringMatching(/^\/training\/seances\//))
    expect(session.workout.value).toBeNull()
  })

  it('discards the workout in two taps', async () => {
    const session = useWorkoutSession()
    await session.start(plan)
    const wrapper = await openWorkout('?terminer=1')

    await button(wrapper, 'Abandonner la séance').trigger('click')
    await button(wrapper, 'Confirmer l’abandon (rien n’est gardé)').trigger('click')
    await settle()
    expect(navigate).toHaveBeenCalledWith('/training')
    expect((await queued()).at(-1)?.op).toBe('discard')
  })

  it('announces a record once the API reports it', async () => {
    fake.on('PUT /api/workouts/{id}', () => ({}))
    let recorded = false
    fake.on('PUT /api/workouts/{workoutId}/sets/{id}', () => {
      recorded = true
      return null
    })
    const session = useWorkoutSession()
    const id = await session.start({ ...plan, exercises: [{ ...plan.exercises[0]!, warmupSets: 0 }] })
    fake.on('GET /api/workouts/{id}', () => ({
      id, status: 'in_progress', programDay: plan.programDay, startedAt: new Date().toISOString(), finishedAt: null, sets: [],
      exercises: [{ ...plan.exercises[0]!, warmupSets: 0, newRecords: recorded ? [{ kind: 'load', value: 85, loadKg: null, date: new Date().toISOString(), workoutId: id }] : [] }],
    }))
    const wrapper = await openWorkout()
    expect(wrapper.text()).not.toContain('Nouveau record')

    await wrapper.get('form').trigger('submit')
    await vi.waitFor(() => expect(wrapper.text()).toContain('Nouveau record\u00A0! Développé couché\u00A0— charge max\u00A0: 85\u00A0kg'))
    expect(wrapper.text()).toContain('Records battus aujourd’hui')
  })

  it('alerts once after an hour', async () => {
    const vibrate = vi.fn()
    vi.stubGlobal('navigator', Object.assign(Object.create(navigator), { vibrate }))
    const session = useWorkoutSession()
    await session.start(plan, new Date(Date.now() - 61 * 60_000))
    await openWorkout()
    await vi.waitFor(() => expect(session.workout.value?.hourAlerted).toBe(true))
    await openWorkout()

    expect(vibrate).toHaveBeenCalledOnce()
  })

  it('goes back to training without a workout in progress', async () => {
    await mountSuspended(WorkoutPage, { route: '/training/seance' })
    await vi.waitFor(() => expect(navigate).toHaveBeenCalledWith('/training'))
  })
})
