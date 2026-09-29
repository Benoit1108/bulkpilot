import type { components } from '~/types/api'
import type { ApiClient } from '~/services/api/client'
import { asApiError } from '~/services/api/client'
import type { ApiError } from '~/services/api/errors'
import { messageFor } from '~/services/api/messages'
import { deviceOwner, onDeviceReset } from '~/services/offline/owner'
import { offlineStore } from '~/services/offline/store'

type StartBody = components['schemas']['Workout.StartWorkoutInput']
type SetBody = components['schemas']['WorkoutSet.WorkoutSetInput']

/**
 * A workout write made on the device, sent to the API in order (spec §116–117). Every one is
 * idempotent on the server: sending it again after a lost answer changes nothing.
 */
export type WorkoutWrite
  = | { op: 'start', workoutId: string, body: StartBody }
    | { op: 'add_exercise', workoutId: string, index: number, exerciseId: string }
    | { op: 'record_set', workoutId: string, setId: string, body: SetBody }
    | { op: 'remove_set', workoutId: string, setId: string }
    | { op: 'finish', workoutId: string, finishedAt: string }
    | { op: 'discard', workoutId: string }

export async function sendWrite(api: ApiClient, write: WorkoutWrite): Promise<void> {
  switch (write.op) {
    case 'start':
      await api.put('/api/workouts/{id}', write.body, { path: { id: write.workoutId } })
      return
    case 'add_exercise':
      await api.put('/api/workouts/{workoutId}/exercises/{index}', { exerciseId: write.exerciseId }, { path: { workoutId: write.workoutId, index: String(write.index) } })
      return
    case 'record_set':
      await api.put('/api/workouts/{workoutId}/sets/{id}', write.body, { path: { workoutId: write.workoutId, id: write.setId } })
      return
    case 'remove_set':
      await api.delete('/api/workouts/{workoutId}/sets/{id}', { path: { workoutId: write.workoutId, id: write.setId } })
      return
    case 'finish':
      await api.post('/api/workouts/{id}/finish', { finishedAt: write.finishedAt }, { path: { id: write.workoutId } })
      return
    case 'discard':
      try {
        await api.delete('/api/workouts/{id}', { path: { id: write.workoutId } })
      }
      catch (error) {
        // Unknown to the API (its start never went through): there is nothing left to discard.
        if (asApiError(error).kind !== 'not_found') throw error
      }
  }
}

/**
 * Worth sending again later: no network, a server hiccup, throttling, another device writing the
 * same workout at that very moment, or a session that ended (sent again once signed in, never
 * with someone else's session: see deviceOwner()). Anything else will not get better by retrying.
 */
export function isTransient(error: ApiError): boolean {
  return error.kind === 'network' || error.kind === 'server' || error.kind === 'rate_limited' || error.kind === 'unauthenticated'
    || (error.kind === 'conflict' && error.code === 'shared.concurrent_update')
}

export interface FailedWrite {
  seq: number
  write: WorkoutWrite
  message: string
}

const RETRY_MS = [5_000, 15_000, 30_000, 60_000]

const state = reactive({ pending: 0, failed: [] as FailedWrite[], syncing: false })
const listeners = new Set<(write: WorkoutWrite) => void>()
let client: ApiClient | null = null
let timer: ReturnType<typeof setTimeout> | undefined
let retries = 0
let running: Promise<void> | null = null
/** A write was queued during a flush: look at the queue again before stopping. */
let again = false
const onOnline = () => void flush()
const onVisible = () => {
  if (document.visibilityState === 'visible') void flush()
}

async function refreshCounts(): Promise<void> {
  const queue = await (await offlineStore()).queue<WorkoutWrite>()
  state.pending = queue.filter(item => item.status === 'pending').length
  state.failed = queue.filter(item => item.status === 'failed').map(item => ({ seq: item.seq, write: item.write, message: item.error ?? '' }))
}

function scheduleRetry(): void {
  clearTimeout(timer)
  timer = setTimeout(() => void flush(), RETRY_MS[Math.min(retries, RETRY_MS.length - 1)])
  retries++
}

/** @returns false when the write has to wait (and every one after it) */
async function send(api: ApiClient, seq: number, write: WorkoutWrite): Promise<boolean> {
  const store = await offlineStore()
  try {
    await sendWrite(api, write)
    await store.dequeue(seq)
    listeners.forEach(listener => listener(write))
    return true
  }
  catch (caught) {
    const error = asApiError(caught)
    if (isTransient(error)) return false
    await store.update({ seq, write, status: 'failed', error: messageFor(error) })
    return true
  }
}

async function sendPending(api: ApiClient): Promise<'done' | 'wait'> {
  const queue = await (await offlineStore()).queue<WorkoutWrite>()
  // A workout the API did not accept: its later writes wait with it (they would all fail).
  const blocked = new Set(queue.filter(item => item.status === 'failed' && item.write.op === 'start').map(item => item.write.workoutId))
  for (const item of queue) {
    if (item.status !== 'pending' || blocked.has(item.write.workoutId)) continue
    if (!(await send(api, item.seq, item.write))) return 'wait'
  }
  return 'done'
}

/** Sends what is pending, in order; stops at the first write that has to wait. Only for the device's owner. */
export function flush(): Promise<void> {
  const api = client
  if (!api || !deviceOwner()) return Promise.resolve()
  if (running) {
    again = true
    return running
  }
  running = (async () => {
    state.syncing = true
    try {
      let outcome: 'done' | 'wait'
      do {
        again = false
        outcome = await sendPending(api)
      } while (outcome === 'done' && again)
      if (outcome === 'wait') scheduleRetry()
      else {
        retries = 0
        clearTimeout(timer)
      }
    }
    finally {
      await refreshCounts()
      state.syncing = false
      running = null
    }
  })()
  return running
}

/** Starts the queue (once per page): what was left from before is sent as soon as possible. */
export function startWorkoutOutbox(api: ApiClient): void {
  if (client) return
  client = api
  if (import.meta.client) {
    window.addEventListener('online', onOnline)
    document.addEventListener('visibilitychange', onVisible)
  }
  void refreshCounts().then(() => flush())
}

/**
 * The queue of workout writes, shared by the whole app: queued writes are kept on the device and
 * sent as soon as possible (now, when the connection comes back, or a little later).
 */
export function useWorkoutOutbox() {
  startWorkoutOutbox(useApi())

  return {
    state: readonly(state),
    /** Keeps the write (with the value it goes with, in the same transaction) and sends it. */
    async queue(write: WorkoutWrite, alongWith?: { key: string, value: unknown }): Promise<void> {
      const store = await offlineStore()
      if (alongWith) await store.commit(alongWith.key, alongWith.value, write)
      else await store.enqueue(write)
      await refreshCounts()
      void flush()
    },
    flush,
    /** Tries the failed writes again (after the user fixed what was wrong, or just to see). */
    async retryFailed(): Promise<void> {
      const store = await offlineStore()
      for (const item of await store.queue<WorkoutWrite>()) {
        if (item.status === 'failed') await store.update({ ...item, status: 'pending', error: null })
      }
      await refreshCounts()
      await flush()
    },
    /** Gives up the failed writes, and those waiting on them (the user accepts losing them). */
    async dropFailed(): Promise<void> {
      const store = await offlineStore()
      const queue = await store.queue<WorkoutWrite>()
      const blocked = new Set(queue.filter(item => item.status === 'failed' && item.write.op === 'start').map(item => item.write.workoutId))
      for (const item of queue) {
        if (item.status === 'failed' || blocked.has(item.write.workoutId)) await store.dequeue(item.seq)
      }
      await refreshCounts()
    },
    /** Whether the device still has to tell the API this workout is over (finished or discarded). */
    async isEnding(workoutId: string): Promise<boolean> {
      const queue = await (await offlineStore()).queue<WorkoutWrite>()
      return queue.some(item => item.write.workoutId === workoutId && (item.write.op === 'finish' || item.write.op === 'discard'))
    },
    /** Called with each write the API acknowledged. */
    onSent(listener: (write: WorkoutWrite) => void): () => void {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
  }
}

/** Forgets everything in memory (the device data went, or between tests). */
export function resetWorkoutOutbox(): void {
  if (import.meta.client) {
    window.removeEventListener('online', onOnline)
    document.removeEventListener('visibilitychange', onVisible)
  }
  client = null
  clearTimeout(timer)
  retries = 0
  running = null
  again = false
  listeners.clear()
  state.pending = 0
  state.failed = []
  state.syncing = false
}

onDeviceReset(resetWorkoutOutbox)
