import { describe, expect, it } from 'vitest'
import { startingValues, supersetLine } from '~/features/training/nextSet'
import { workoutExercise, workoutSet } from '../support/training'

const squat = workoutExercise(0, 0, { name: 'Squat', incrementKg: 2.5, repMin: 6, repMax: 10, suggestion: { reason: 'increase_load', loadKg: 102.5, reps: 6, seconds: null } })
const plank = workoutExercise(1, 1, { name: 'Gainage', measurement: 'duration', repMin: 30, repMax: 60 })

describe('the next set starts from', () => {
  it.each([
    ['the suggestion, for the first working set', squat, [], 'normal', { kind: 'normal', loadKg: 102.5, reps: 6, seconds: null }],
    ['last time, without a suggestion', { ...squat, suggestion: null, previous: { date: '2026-09-21T18:00:00Z', sets: [workoutSet(0, { kind: 'warmup', loadKg: 40 }), workoutSet(0, { loadKg: 100, reps: 8 })] } }, [], 'normal', { kind: 'normal', loadKg: 100, reps: 8, seconds: null }],
    ['the top of the range, the first time ever', { ...squat, suggestion: null }, [], 'normal', { kind: 'normal', reps: 10, seconds: null }],
    ['the set just done, with its RIR', squat, [workoutSet(0, { loadKg: 105, reps: 7, rir: 1 })], 'normal', { kind: 'normal', loadKg: 105, reps: 7, seconds: null, rir: 1 }],
    ['half the working load for a warm-up, on the plate step', squat, [], 'warmup', { kind: 'warmup', loadKg: 52.5, reps: 10, seconds: null }],
    ['the warm-up just done, for the next one', squat, [workoutSet(0, { kind: 'warmup', loadKg: 60, reps: 5 })], 'warmup', { kind: 'warmup', loadKg: 60, reps: 5, seconds: null }],
    ['the shortest hold, for a timed warm-up', plank, [], 'warmup', { kind: 'warmup', loadKg: null, reps: null, seconds: 30 }],
    ['a quarter off the last working set, for the drop set', squat, [workoutSet(0, { loadKg: 100, reps: 8 })], 'drop', { kind: 'drop', loadKg: 75, reps: 8, seconds: null }],
    ['no load for a drop set after a bodyweight set', squat, [workoutSet(0, { loadKg: null, reps: 12 })], 'drop', { kind: 'drop', loadKg: 0, reps: 12, seconds: null }],
  ] as const)('%s', (_, exercise, own, kind, expected) => {
    expect(startingValues(exercise, own, kind)).toEqual(expected)
  })
})

describe('a superset line', () => {
  const a1 = workoutExercise(0, 0, { name: 'Développé' })
  const b1 = workoutExercise(1, 1, { name: 'Élévations' })
  const b2 = workoutExercise(2, 1, { name: 'Gainage', restSeconds: 90 })
  const all = [a1, b1, b2]

  it('names what comes next without rest, then the rest after the round', () => {
    expect(supersetLine(b1, all)).toBe('Superset B · 1/2\u00A0— ensuite\u00A0: Gainage (sans repos)')
    expect(supersetLine(b2, all)).toBe('Superset B · 2/2\u00A0— puis repos\u00A0: 1\u00A0min\u00A030')
  })

  it('says nothing for an exercise on its own', () => {
    expect(supersetLine(a1, all)).toBeNull()
  })
})
