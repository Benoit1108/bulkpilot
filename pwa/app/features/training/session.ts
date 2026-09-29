import { plannedFrom, type Exercise } from '~/features/training/training'
import { useWorkoutOutbox, type WorkoutWrite } from '~/features/training/outbox'
import { afterSet, type ProgramDayRef, type SetKind, type Workout, type WorkoutExercise, type WorkoutPlan, type WorkoutSet } from '~/features/training/workout'
import { onDeviceReset } from '~/services/offline/owner'
import { offlineStore } from '~/services/offline/store'

/**
 * The workout in progress, kept on the device (spec §45): it survives a reload, the app being
 * closed, the phone locked or offline, and each change is queued for the API in the same
 * transaction. The device is the reference while training; the API adds what only it knows
 * (records, suggestions).
 */
export interface LocalWorkout {
  id: string
  startedAt: string
  programDay: ProgramDayRef | null
  exercises: WorkoutExercise[]
  sets: WorkoutSet[]
  /** The exercise on screen. */
  current: number
  /** Rest running (endsAt) or paused (remaining), in milliseconds. */
  rest: { totalMs: number, endsAt: number | null, remainingMs: number | null } | null
  /** The one-hour alert was given (spec §44). */
  hourAlerted: boolean
}

export interface SetValues {
  kind: SetKind
  loadKg: number | null
  reps: number | null
  seconds: number | null
  rir: number | null
}

const KEY = 'training.workout'
/** An hour: the spec's alert. */
export const LONG_WORKOUT_MS = 60 * 60 * 1000

const workout = ref<LocalWorkout | null>(null)
const loaded = ref(false)
let loading: Promise<void> | null = null
/** Bumped by every change: a load that started before one must not bring back an older copy. */
let changes = 0

/** crypto.randomUUID needs a secure context and a recent Safari; getRandomValues is everywhere. */
function uuid(): string {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID()
  const bytes = crypto.getRandomValues(new Uint8Array(16))
  bytes[6] = (bytes[6]! & 0x0F) | 0x40
  bytes[8] = (bytes[8]! & 0x3F) | 0x80
  const hex = [...bytes].map(byte => byte.toString(16).padStart(2, '0')).join('')
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}

export function useWorkoutSession() {
  const outbox = useWorkoutOutbox()

  async function save(write?: WorkoutWrite): Promise<void> {
    changes++
    // No workout any more is kept as null, in the same transaction as its last write.
    if (write) await outbox.queue(write, { key: KEY, value: workout.value })
    else await (await offlineStore()).set(KEY, workout.value)
  }

  async function change(update: (current: LocalWorkout) => void, write?: WorkoutWrite): Promise<void> {
    if (!workout.value) return
    update(workout.value)
    await save(write)
  }

  return {
    // Changed through the methods below only (each one keeps and queues its write).
    workout: computed(() => workout.value),
    loaded: computed(() => loaded.value),

    async load(): Promise<void> {
      loading ??= (async () => {
        const before = changes
        const kept = await (await offlineStore()).get<LocalWorkout>(KEY) ?? null
        if (changes === before) {
          // A rest that ended while the app was closed is over.
          if (kept?.rest?.endsAt && kept.rest.endsAt <= Date.now()) kept.rest = null
          workout.value = kept
        }
        loaded.value = true
      })()
      await loading
    },

    /** Starts from a plan (a program day) or empty (a free workout). */
    async start(plan: WorkoutPlan | null, now = new Date()): Promise<string> {
      const id = uuid()
      const startedAt = now.toISOString()
      workout.value = {
        id,
        startedAt,
        programDay: plan?.programDay ?? null,
        exercises: plan ? plan.exercises.map(exercise => ({ ...exercise, newRecords: [] })) : [],
        sets: [],
        current: 0,
        rest: null,
        hourAlerted: false,
      }
      const day = plan?.programDay
      await save({ op: 'start', workoutId: id, body: { startedAt, dayIndex: day?.dayIndex ?? 0, ...(day && { programId: day.programId, versionNumber: day.versionNumber }) } })
      return id
    },

    /** Picks up, on this device, the workout the API has in progress (started elsewhere, or data cleared). */
    async resume(server: Workout): Promise<void> {
      workout.value = {
        id: server.id,
        startedAt: server.startedAt,
        programDay: server.programDay,
        exercises: server.exercises,
        sets: server.sets,
        current: 0,
        rest: null,
        hourAlerted: Date.now() - new Date(server.startedAt).getTime() >= LONG_WORKOUT_MS,
      }
      await save()
    },

    async recordSet(values: SetValues, now = new Date()): Promise<WorkoutSet | null> {
      const current = workout.value
      if (!current) return null
      const set: WorkoutSet = { id: uuid(), exerciseIndex: current.current, ...values, doneAt: now.toISOString() }
      const { next, restSeconds } = afterSet(current.exercises, [...current.sets, set], current.current, values.kind)
      await change((local) => {
        local.sets.push(set)
        local.current = next
        local.rest = restSeconds > 0 ? { totalMs: restSeconds * 1000, endsAt: now.getTime() + restSeconds * 1000, remainingMs: null } : null
      }, { op: 'record_set', workoutId: current.id, setId: set.id, body: { exerciseIndex: set.exerciseIndex, ...values, doneAt: set.doneAt } })
      return set
    },

    async correctSet(setId: string, values: SetValues): Promise<void> {
      const current = workout.value
      const set = current?.sets.find(recorded => recorded.id === setId)
      if (!current || !set) return
      Object.assign(set, values)
      await save({ op: 'record_set', workoutId: current.id, setId, body: { exerciseIndex: set.exerciseIndex, ...values, doneAt: set.doneAt } })
    },

    async removeSet(setId: string): Promise<void> {
      const current = workout.value
      if (!current) return
      await change((local) => {
        local.sets = local.sets.filter(set => set.id !== setId)
      }, { op: 'remove_set', workoutId: current.id, setId })
    },

    /** The API gives it the same usual values and a block of its own (WorkoutPlanner, Workout::addExercise). */
    async addExercise(exercise: Exercise): Promise<void> {
      const current = workout.value
      if (!current) return
      const index = current.exercises.length
      const block = Math.max(-1, ...current.exercises.map(planned => planned.block)) + 1
      await change((local) => {
        local.exercises.push({ index, block, added: true, name: exercise.name, measurement: exercise.measurement, note: exercise.note, programNotes: null, previous: null, suggestion: null, records: [], newRecords: [], ...plannedFrom(exercise) })
        local.current = index
      }, { op: 'add_exercise', workoutId: current.id, index, exerciseId: exercise.id })
    },

    async goTo(index: number): Promise<void> {
      await change((local) => {
        if (local.exercises[index]) local.current = index
      })
    },

    async pauseRest(now = Date.now()): Promise<void> {
      await change((local) => {
        if (local.rest?.endsAt) local.rest = { ...local.rest, endsAt: null, remainingMs: Math.max(0, local.rest.endsAt - now) }
      })
    },

    async resumeRest(now = Date.now()): Promise<void> {
      await change((local) => {
        if (local.rest && local.rest.remainingMs !== null) local.rest = local.rest.remainingMs > 0 ? { ...local.rest, endsAt: now + local.rest.remainingMs, remainingMs: null } : null
      })
    },

    /** ±15 s; brought to zero, the rest is over. */
    async adjustRest(seconds: number, now = Date.now()): Promise<void> {
      await change((local) => {
        const rest = local.rest
        if (!rest) return
        const delta = seconds * 1000
        const remaining = rest.endsAt !== null ? rest.endsAt - now + delta : (rest.remainingMs ?? 0) + delta
        if (remaining <= 0) {
          local.rest = null
          return
        }
        local.rest = { totalMs: Math.max(remaining, rest.totalMs + delta), endsAt: rest.endsAt !== null ? now + remaining : null, remainingMs: rest.endsAt !== null ? null : remaining }
      })
    },

    async endRest(): Promise<void> {
      await change((local) => {
        local.rest = null
      })
    },

    async markHourAlerted(): Promise<void> {
      await change((local) => {
        local.hourAlerted = true
      })
    },

    /** Records, suggestions and cues come from the API: taken whenever it answers; the sets stay the device's. */
    async merge(server: Workout): Promise<void> {
      await change((local) => {
        if (local.id !== server.id) return
        local.exercises = local.exercises.map(exercise => server.exercises.find(known => known.index === exercise.index) ?? exercise)
      })
    },

    async finish(now = new Date()): Promise<string | null> {
      const current = workout.value
      if (!current) return null
      workout.value = null
      await save({ op: 'finish', workoutId: current.id, finishedAt: now.toISOString() })
      return current.id
    },

    async discard(): Promise<void> {
      const current = workout.value
      if (!current) return
      workout.value = null
      await save({ op: 'discard', workoutId: current.id })
    },
  }
}

/** Forgets the workout in memory (the device data went, or between tests). */
export function resetWorkoutSession(): void {
  workout.value = null
  loaded.value = false
  loading = null
  changes = 0
}

onDeviceReset(resetWorkoutSession)
