import 'fake-indexeddb/auto'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import { resetWorkoutOutbox, useWorkoutOutbox, type WorkoutWrite } from '~/features/training/outbox'
import { ApiError } from '~/services/api/errors'
import { deviceOwner, releaseDevice } from '~/services/offline/owner'
import { offlineStore } from '~/services/offline/store'
import { alex, createFakeApi } from '../support/fakeApi'

const fake = createFakeApi()
mockNuxtImport('useApi', () => () => fake.client)
const { navigate } = vi.hoisted(() => ({ navigate: vi.fn() }))
mockNuxtImport('navigateTo', () => navigate)

const start: WorkoutWrite = { op: 'start', workoutId: 'w1', body: { startedAt: '2026-09-28T18:00:00Z', dayIndex: 0 } }
const unreachable = () => new ApiError('network', null, 'x', 'offline')
const meCalls = () => fake.calls.filter(call => call.route === 'GET /api/me').length
const sent = () => fake.calls.filter(call => call.route === 'PUT /api/workouts/{id}').map(call => call.body)

// The plugin itself is the app's: registered once when the test app boots (calling it again
// would add a second set of listeners). Each test puts the app in the state it opens in.
beforeEach(async () => {
  fake.reset()
  navigate.mockReset()
  resetWorkoutOutbox()
  releaseDevice()
  await (await offlineStore()).clear()
  // Opened in the gym: the account could not be read.
  useState(ACCOUNT_STATE_KEY).value = undefined
  await flushPromises()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('the offline plugin', () => {
  it('sends what was recorded offline once the network is back, without a reload', async () => {
    fake.on('PUT /api/workouts/{id}', () => ({}))
    await useWorkoutOutbox().queue(start)
    expect(deviceOwner()).toBeNull()

    fake.on('GET /api/me', () => alex)
    window.dispatchEvent(new Event('online'))
    await vi.waitFor(() => expect(sent()).toEqual([start.body]))
    expect(deviceOwner()).toBe(alex.id)
    expect(useWorkoutOutbox().state.pending).toBe(0)
  })

  it('keeps waiting while the server is still out of reach', async () => {
    fake.on('PUT /api/workouts/{id}', () => ({}))
    await useWorkoutOutbox().queue(start)
    fake.on('GET /api/me', () => {
      throw unreachable()
    })
    window.dispatchEvent(new Event('online'))
    await flushPromises()

    expect(useState(ACCOUNT_STATE_KEY).value).toBeUndefined()
    expect(sent()).toEqual([])
    expect(useWorkoutOutbox().state.pending).toBe(1)
  })

  it('asks again when the app comes back to the foreground, once for both events', async () => {
    fake.on('GET /api/me', () => alex)
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    // The phone unlocked: both events at once.
    window.dispatchEvent(new Event('online'))
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(meCalls()).toBe(1)
    expect(useState(ACCOUNT_STATE_KEY).value).toEqual(alex)
  })

  it('leaves for the sign-in page when the session expired meanwhile', async () => {
    fake.on('GET /api/me', () => null)
    window.dispatchEvent(new Event('online'))
    await flushPromises()

    expect(useState(ACCOUNT_STATE_KEY).value).toBeNull()
    expect(navigate).toHaveBeenCalledWith(expect.objectContaining({ path: '/connexion' }))
  })
})
