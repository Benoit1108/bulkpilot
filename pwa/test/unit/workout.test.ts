import { describe, expect, it } from 'vitest'
import { afterSet, dropSetDue, formatClock, formatRecord, formatSet, formatSuggestion, nextKind, spokenDuration, unfinished, workoutDuration } from '~/features/training/workout'
import { workoutExercise as exercise, workoutSet as set } from '../support/training'

describe('workout formats', () => {
  it('writes sets, records, suggestions and times', () => {
    expect(formatSet({ loadKg: 82.5, reps: 8, seconds: null, rir: 2 }, 'weight')).toBe('82,5\u00A0kg × 8 · RIR\u00A02')
    expect(formatSet({ loadKg: 10, reps: 12, seconds: null, rir: null }, 'bodyweight')).toBe('+10\u00A0kg × 12')
    expect(formatSet({ loadKg: null, reps: 12, seconds: null, rir: null }, 'bodyweight')).toBe('12\u00A0reps')
    expect(formatSet({ loadKg: null, reps: null, seconds: 45, rir: null }, 'duration')).toBe('45\u00A0s')
    expect(formatSet({ loadKg: null, reps: null, seconds: null, rir: null }, 'weight')).toBe('0\u00A0kg × 0')

    const record = { kind: 'load' as const, value: 100, loadKg: null, date: '2026-09-28T18:00:00Z', workoutId: 'w' }
    expect(formatRecord(record, 'weight')).toBe('charge max\u00A0: 100\u00A0kg')
    expect(formatRecord({ ...record, kind: 'reps_at_load', value: 9, loadKg: 80 }, 'weight')).toBe('répétitions à cette charge\u00A0: 9\u00A0reps à 80\u00A0kg')
    expect(formatRecord({ ...record, kind: 'duration', value: 70 }, 'duration')).toBe('durée\u00A0: 70\u00A0s')
    expect(formatRecord({ ...record, kind: 'reps', value: 15 }, 'bodyweight')).toBe('répétitions\u00A0: 15\u00A0reps')

    const row = exercise(0, 0)
    expect(formatSuggestion({ reason: 'increase_load', loadKg: 82.5, reps: 8, seconds: null }, row)).toBe('82,5\u00A0kg × 8 (toutes les séries au maximum la dernière fois)')
    expect(formatSuggestion({ reason: 'add_reps', loadKg: null, reps: null, seconds: 50 }, row)).toBe('50\u00A0s (une répétition de plus)')
    expect(formatSuggestion({ reason: 'repeat', loadKg: 0, reps: 10, seconds: null }, { ...row, measurement: 'bodyweight' })).toBe('10\u00A0reps (objectif pas encore atteint)')
    expect(formatSuggestion({ reason: 'decrease_load', loadKg: 5, reps: null, seconds: null }, { ...row, measurement: 'bodyweight' })).toBe('+5\u00A0kg × 8 (séries plus dures que prévu)')

    expect(formatClock(0)).toBe('00:00')
    expect(formatClock(92_400)).toBe('01:32')
    expect(formatClock(3_909_000)).toBe('1:05:09')
    expect(formatClock(-5)).toBe('00:00')
    expect(spokenDuration(90_000)).toBe('1\u00A0min 30\u00A0s')
    expect(spokenDuration(3_600_000)).toBe('1\u00A0h')
    expect(spokenDuration(0)).toBe('0\u00A0s')
    expect(workoutDuration({ startedAt: '2026-09-28T18:00:00Z', finishedAt: '2026-09-28T19:05:00Z' })).toBe(3_900_000)
    expect(workoutDuration({ startedAt: '2026-09-28T18:00:00Z', finishedAt: null })).toBeNull()
  })
})

describe('the way through a workout', () => {
  const plan = [exercise(0, 0), exercise(1, 1, { restSeconds: 45 }), exercise(2, 1, { restSeconds: 150 }), exercise(3, 2)]

  it('stays on an exercise until its sets are done, then moves on', () => {
    const first = [set(0)]
    expect(afterSet(plan, first, 0, 'normal')).toEqual({ next: 0, restSeconds: 90 })
    expect(afterSet(plan, [...first, set(0)], 0, 'normal')).toEqual({ next: 1, restSeconds: 90 })
  })

  it('goes through a superset without rest, resting after the round', () => {
    expect(afterSet(plan, [set(1)], 1, 'normal'), 'A1 → A2 whatever A1 says').toEqual({ next: 2, restSeconds: 0 })
    expect(afterSet(plan, [set(1), set(2)], 2, 'normal'), 'the round’s rest').toEqual({ next: 1, restSeconds: 150 })
    expect(afterSet(plan, [set(1), set(2), set(1), set(2)], 2, 'normal')).toEqual({ next: 3, restSeconds: 150 })
    expect(afterSet(plan, [set(1), set(2), set(2)], 2, 'normal'), 'A2 done, A1 left').toEqual({ next: 1, restSeconds: 150 })
  })

  it('keeps the user where they are after a warm-up, and offers the planned drop set right away', () => {
    expect(afterSet(plan, [set(0, { kind: 'warmup' })], 0, 'warmup')).toEqual({ next: 0, restSeconds: 90 })
    expect(afterSet([exercise(0, 0, { restSeconds: 180 })], [set(0, { kind: 'approach' })], 0, 'approach'), 'short, whatever the exercise').toEqual({ next: 0, restSeconds: 90 })
    expect(afterSet(plan, [set(0, { kind: 'drop' })], 0, 'drop')).toEqual({ next: 0, restSeconds: 0 })
    expect(afterSet(plan, [], 9, 'normal')).toEqual({ next: 9, restSeconds: 0 })

    const withDrop = [exercise(0, 0, { dropSet: true }), exercise(1, 1)]
    const worked = [set(0), set(0)]
    expect(dropSetDue(withDrop[0]!, worked)).toBe(true)
    expect(afterSet(withDrop, worked, 0, 'normal'), 'the drop set now').toEqual({ next: 0, restSeconds: 0 })
    expect(nextKind(withDrop[0]!, worked)).toBe('drop')
    expect(afterSet(withDrop, [...worked, set(0, { kind: 'drop' })], 0, 'drop')).toEqual({ next: 0, restSeconds: 0 })
    expect(dropSetDue(withDrop[0]!, [...worked, set(0, { kind: 'drop' })])).toBe(false)
  })

  it('knows a planned warm-up comes first', () => {
    const warmed = exercise(0, 0, { warmupSets: 2 })
    expect(nextKind(warmed, [])).toBe('warmup')
    expect(nextKind(warmed, [set(0, { kind: 'warmup' })])).toBe('warmup')
    expect(nextKind(warmed, [set(0, { kind: 'warmup' }), set(0, { kind: 'warmup' })])).toBe('normal')
    expect(nextKind(warmed, [set(0)]), 'working already: no more warm-up').toBe('normal')
  })

  it('stays on the last exercise when everything is done', () => {
    const all = [set(0), set(0), set(1), set(2), set(1), set(2), set(3), set(3)]
    expect(afterSet(plan, all, 3, 'normal')).toEqual({ next: 3, restSeconds: 90 })
    expect(unfinished(plan, all)).toEqual([])
    expect(unfinished(plan, [set(0), set(0, { kind: 'warmup' })]).map(row => row.index)).toEqual([0, 1, 2, 3])
  })
})
