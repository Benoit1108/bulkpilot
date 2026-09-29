import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import type { Workout } from '~/features/training/workout'
import { useWorkoutRecords } from '~/features/training/workoutRecords'
import { workoutExercise } from '../support/training'

const { answers, merge } = vi.hoisted(() => ({ answers: [] as Array<(workout: Workout) => void>, merge: vi.fn() }))
// Only the workout is held until the test answers: the app's own start-up calls fail at once.
mockNuxtImport('useApi', () => () => ({
  get: (path: string) => (path === '/api/workouts/{id}' ? new Promise(resolve => answers.push(resolve)) : Promise.reject(new Error('offline'))),
}))
vi.mock('~/features/training/session', async importOriginal => ({
  ...await importOriginal<typeof import('~/features/training/session')>(),
  useWorkoutSession: () => ({ workout: ref({ id: 'w1' }), merge }),
}))

const record = { kind: 'load' as const, value: 100, loadKg: 100, date: '2026-09-29T18:00:00Z', workoutId: 'w1' }
const server = (newRecords: Workout['exercises'][number]['newRecords']): Workout => ({
  id: 'w1', status: 'in_progress', programDay: null, startedAt: '2026-09-29T18:00:00Z', finishedAt: null, sets: [],
  exercises: [workoutExercise(0, 0, { name: 'Squat', newRecords })],
})

beforeEach(() => {
  answers.length = 0
  merge.mockReset()
})

describe('records during a workout', () => {
  it('drops an answer older than the last one asked for', async () => {
    const notice = ref<string | null>(null)
    const { refresh } = useWorkoutRecords(notice)
    const first = refresh()
    const second = refresh()

    answers[1]!(server([]))
    await second
    answers[0]!(server([record]))
    await first

    expect(merge).toHaveBeenCalledOnce()
    expect(notice.value, 'the stale answer announces nothing').toBeNull()
  })

  it('announces each new record once', async () => {
    const notice = ref<string | null>(null)
    const { refresh } = useWorkoutRecords(notice)

    const first = refresh()
    answers[0]!(server([record]))
    await first
    expect(notice.value).toMatch(/^Nouveau record\u00A0! Squat\u00A0— /)

    notice.value = null
    const again = refresh()
    answers[1]!(server([record]))
    await again
    expect(notice.value).toBeNull()
  })
})
