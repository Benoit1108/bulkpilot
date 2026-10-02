import 'fake-indexeddb/auto'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import SyncStatus from '~/features/training/components/SyncStatus.vue'
import { flush, isTransient, resetWorkoutOutbox, useWorkoutOutbox, type WorkoutWrite } from '~/features/training/outbox'
import { ApiError } from '~/services/api/errors'
import { messageFor } from '~/services/api/messages'
import { claimDevice, releaseDevice } from '~/services/offline/owner'
import { forgetOfflineStore, offlineStore } from '~/services/offline/store'
import { button } from '../support/dom'
import { apiError, createFakeApi } from '../support/fakeApi'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)
const fake = createFakeApi()
api.current = fake

const start: WorkoutWrite = { op: 'start', workoutId: 'w1', body: { startedAt: '2026-09-28T18:00:00Z', dayIndex: 0 } }
const set = (id: string): WorkoutWrite => ({ op: 'record_set', workoutId: 'w1', setId: id, body: { exerciseIndex: 0, kind: 'normal', loadKg: 80, reps: 8, seconds: null, rir: null, doneAt: '2026-09-28T18:05:00Z' } })
const offline = () => new ApiError('network', null, 'x', 'offline')

async function queued(): Promise<WorkoutWrite[]> {
  return (await (await offlineStore()).queue<WorkoutWrite>()).map(item => item.write)
}

beforeEach(async () => {
  fake.reset()
  resetWorkoutOutbox()
  await (await offlineStore()).clear()
  await claimDevice('alex')
})

afterEach(() => {
  vi.useRealTimers()
  forgetOfflineStore()
})

describe('the outbox', () => {
  it('sends writes in order and keeps them while the network is down', async () => {
    let online = false
    fake.on('PUT /api/workouts/{id}', () => {
      if (!online) throw offline()
      return {}
    })
    fake.on('PUT /api/workouts/{workoutId}/sets/{id}', () => null)
    const outbox = useWorkoutOutbox()
    const sent: string[] = []
    outbox.onSent(write => sent.push(write.op))

    await outbox.queue(start)
    await outbox.queue(set('s1'))
    await flush()
    expect(outbox.state.pending).toBe(2)
    expect(fake.calls.map(call => call.route).filter(route => route.includes('/sets/')), 'nothing after a write that waits').toEqual([])

    online = true
    await flush()
    expect(sent).toEqual(['start', 'record_set'])
    expect(outbox.state.pending).toBe(0)
    expect(await queued()).toEqual([])
  })

  it('sends a write queued while a flush is running', async () => {
    let answer: () => void = () => {}
    fake.on('PUT /api/workouts/{id}', () => new Promise<void>(resolve => (answer = resolve)))
    fake.on('PUT /api/workouts/{workoutId}/sets/{id}', () => null)
    const outbox = useWorkoutOutbox()

    await outbox.queue(start)
    await outbox.queue(set('s1'))
    answer()
    await flushPromises()
    await flush()

    expect(fake.calls.map(call => call.route)).toEqual(['PUT /api/workouts/{id}', 'PUT /api/workouts/{workoutId}/sets/{id}'])
    expect(outbox.state.pending).toBe(0)
  })

  it('sends nothing while the device’s owner is signed out', async () => {
    fake.on('PUT /api/workouts/{id}', () => ({}))
    releaseDevice()
    const outbox = useWorkoutOutbox()
    await outbox.queue(start)
    expect(fake.calls).toEqual([])

    await claimDevice('alex')
    await flush()
    expect(fake.calls).toHaveLength(1)
  })

  it('retries later, then less often', async () => {
    // In memory: IndexedDB's own timers must not be frozen with the retry ones.
    vi.stubGlobal('indexedDB', undefined)
    forgetOfflineStore()
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    let attempts = 0
    fake.on('PUT /api/workouts/{id}', () => {
      attempts++
      throw apiError(503, null)
    })
    await useWorkoutOutbox().queue(start)
    await vi.advanceTimersByTimeAsync(0)
    expect(attempts).toBe(1)
    await vi.advanceTimersByTimeAsync(5_000)
    expect(attempts).toBe(2)
    await vi.advanceTimersByTimeAsync(14_000)
    expect(attempts, 'the next try is 15 s later').toBe(2)
    await vi.advanceTimersByTimeAsync(1_000)
    expect(attempts).toBe(3)
  })

  it('keeps what the API refuses, holds the writes of a refused workout, then retries or gives up', async () => {
    let refuse = true
    fake.on('PUT /api/workouts/{id}', () => {
      if (refuse) throw apiError(409, 'training.workout_in_progress')
      return {}
    })
    fake.on('PUT /api/workouts/{workoutId}/sets/{id}', () => null)
    const outbox = useWorkoutOutbox()
    await outbox.queue(start)
    await outbox.queue(set('s1'))
    await flush()
    expect(outbox.state.failed.map(failed => failed.write.op)).toEqual(['start'])
    expect(outbox.state.failed[0]!.message).toBe(messageFor(apiError(409, 'training.workout_in_progress')))
    expect(outbox.state.pending, 'its set waits with it').toBe(1)
    expect(fake.calls.filter(call => call.route.includes('/sets/'))).toEqual([])

    refuse = false
    await outbox.retryFailed()
    expect([outbox.state.failed.length, outbox.state.pending]).toEqual([0, 0])

    refuse = true
    await outbox.queue({ ...start, workoutId: 'w2' })
    await outbox.queue({ ...set('s2'), workoutId: 'w2' })
    await flush()
    await outbox.dropFailed()
    expect(await queued(), 'dropped with what waited on it').toEqual([])
  })

  it('counts discarding a workout the API never knew as done', async () => {
    fake.on('DELETE /api/workouts/{id}', () => {
      throw apiError(404, 'training.workout_not_found')
    })
    const outbox = useWorkoutOutbox()
    await outbox.queue({ op: 'discard', workoutId: 'w' })
    await flush()

    expect([outbox.state.failed.length, outbox.state.pending]).toEqual([0, 0])
  })

  it('knows which errors are worth another try', () => {
    expect(isTransient(offline())).toBe(true)
    expect(isTransient(apiError(503, null))).toBe(true)
    expect(isTransient(apiError(429, null))).toBe(true)
    expect(isTransient(apiError(401, 'shared.not_authenticated'))).toBe(true)
    expect(isTransient(apiError(409, 'shared.concurrent_update'))).toBe(true)
    expect(isTransient(apiError(409, 'training.workout_in_progress'))).toBe(false)
    expect(isTransient(apiError(422, 'training.invalid_workout'))).toBe(false)
  })

  it('routes every write to its endpoint and knows a workout being ended', async () => {
    for (const route of ['PUT /api/workouts/{workoutId}/exercises/{index}', 'DELETE /api/workouts/{workoutId}/sets/{id}', 'DELETE /api/workouts/{id}', 'POST /api/workouts/{id}/finish']) {
      fake.on(route, () => {
        throw offline()
      })
    }
    const outbox = useWorkoutOutbox()
    await outbox.queue({ op: 'add_exercise', workoutId: 'w', index: 2, exerciseId: 'e' })
    await outbox.queue({ op: 'remove_set', workoutId: 'w', setId: 's' })
    await outbox.queue({ op: 'finish', workoutId: 'w', finishedAt: '2026-09-28T19:00:00Z' })
    expect(await outbox.isEnding('w')).toBe(true)
    expect(await outbox.isEnding('other')).toBe(false)

    for (const route of ['PUT /api/workouts/{workoutId}/exercises/{index}', 'DELETE /api/workouts/{workoutId}/sets/{id}', 'POST /api/workouts/{id}/finish', 'DELETE /api/workouts/{id}']) fake.on(route, () => null)
    await outbox.queue({ op: 'discard', workoutId: 'w' })
    await flush()
    expect(fake.calls.map(call => call.route).slice(-4)).toEqual(['PUT /api/workouts/{workoutId}/exercises/{index}', 'DELETE /api/workouts/{workoutId}/sets/{id}', 'POST /api/workouts/{id}/finish', 'DELETE /api/workouts/{id}'])
    expect(fake.calls.find(call => call.route.includes('/exercises/'))!.params).toEqual({ path: { workoutId: 'w', index: '2' } })
  })
})

describe('the sync status', () => {
  it('says quietly that changes wait, after a moment, and when all is sent', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    let online = false
    const onLine = vi.spyOn(navigator, 'onLine', 'get').mockImplementation(() => online)
    fake.on('PUT /api/workouts/{id}', () => {
      if (!online) throw offline()
      return {}
    })
    const wrapper = await mountSuspended(SyncStatus)
    await useWorkoutOutbox().queue(start)
    await vi.advanceTimersByTimeAsync(1_000)
    expect(wrapper.text()).not.toContain('gardée')

    await vi.advanceTimersByTimeAsync(2_000)
    expect(wrapper.text()).toContain('Hors ligne\u00A0: 1\u00A0modification gardée sur cet appareil, envoyée au retour du réseau.')
    expect(wrapper.get('[role="status"]').text()).toBe('Hors ligne\u00A0: tes séries sont gardées sur cet appareil.')

    online = true
    await flush()
    await flushPromises()
    expect(wrapper.text()).not.toContain('gardée sur cet appareil')
    expect(wrapper.get('[role="status"]').text()).toBe('Tout est envoyé.')
    // Said on screen too, for a moment.
    const shown = () => wrapper.findAll('p:not([role="status"])').map(line => line.text())
    expect(shown()).toContain('Tout est envoyé.')
    await vi.advanceTimersByTimeAsync(3_000)
    expect(shown()).not.toContain('Tout est envoyé.')
    onLine.mockRestore()
  })

  it('does not say offline when only the server is slow', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    fake.on('PUT /api/workouts/{id}', () => {
      throw offline()
    })
    const wrapper = await mountSuspended(SyncStatus)
    await useWorkoutOutbox().queue(start)
    await vi.advanceTimersByTimeAsync(3_000)

    expect(wrapper.text()).toContain('Pas encore envoyé\u00A0: 1\u00A0modification gardée sur cet appareil.')
    expect(wrapper.get('[role="status"]').text()).toBe('Pas encore envoyé\u00A0: gardé sur cet appareil.')
  })

  it('keeps the changes without speaking of a session when the app was opened offline', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    releaseDevice()
    const wrapper = await mountSuspended(SyncStatus)
    // The account could not be read: not known, not signed out.
    useState('account').value = undefined
    await useWorkoutOutbox().queue(start)
    await vi.advanceTimersByTimeAsync(3_000)

    expect(wrapper.text()).toContain('1\u00A0modification gardée sur cet appareil')
    expect(wrapper.text()).not.toContain('Session expirée')
    useState('account').value = null
  })

  it('asks to sign in again when the session ended', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    releaseDevice()
    const wrapper = await mountSuspended(SyncStatus)
    await useWorkoutOutbox().queue(start)
    await vi.advanceTimersByTimeAsync(3_000)

    expect(wrapper.text()).toContain('Session expirée\u00A0: 1\u00A0modification attend ta reconnexion pour être envoyée.')
    expect(wrapper.get('[role="status"]').text()).toBe('Session expirée\u00A0: reconnecte-toi pour envoyer 1\u00A0modification.')
  })

  it('shows what was refused, without taking the focus, and lets the user retry or give up', async () => {
    fake.on('POST /api/workouts/{id}/finish', () => {
      throw apiError(422, 'training.invalid_workout')
    })
    const wrapper = await mountSuspended(SyncStatus, { attachTo: document.body })
    const outbox = useWorkoutOutbox()
    await outbox.queue({ op: 'finish', workoutId: 'w1', finishedAt: '2026-09-28T19:00:00Z' })
    await flush()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain(`1\u00A0modification n’a pas pu être enregistrée\u00A0: ${messageFor(apiError(422, 'training.invalid_workout'))}`)
    expect(document.activeElement).toBe(document.body)

    await button(wrapper, 'Réessayer').trigger('click')
    await vi.waitFor(() => expect(fake.calls.filter(call => call.route === 'POST /api/workouts/{id}/finish')).toHaveLength(2))
    await vi.waitFor(() => expect(wrapper.find('[role="alert"]').exists()).toBe(true))

    await button(wrapper, 'Les ignorer').trigger('click')
    await button(wrapper, 'Confirmer (elles seront perdues)').trigger('click')
    await vi.waitFor(() => expect(wrapper.find('[role="alert"]').exists()).toBe(false))
    expect(await queued()).toEqual([])
  })
})
