import { describe, expect, it } from 'vitest'
import { useProgramDraft } from '~/features/training/programDraft'
import { bench, plank, ppl, raise } from '../support/training'

describe('a program draft', () => {
  it('starts with one empty day, or from the program', () => {
    expect(useProgramDraft().days.value.map(day => [day.name, day.blocks.length])).toEqual([['Jour A', 0]])
    expect(useProgramDraft(ppl).days.value.map(day => day.name)).toEqual(['Push', 'Pull'])
  })

  it('makes a superset rest after its last exercise only', () => {
    const draft = useProgramDraft()
    draft.add(raise, 0, null)
    draft.add(plank, 0, 0)

    expect(draft.days.value[0]!.blocks[0]!.exercises.map(exercise => [exercise.name, exercise.restSeconds])).toEqual([[raise.name, 0], [plank.name, plank.restSeconds]])
  })

  it('passes the round\'s rest on when the last exercise of a superset is removed', () => {
    const draft = useProgramDraft()
    draft.add(raise, 0, null)
    draft.add(plank, 0, 0)
    const day = draft.days.value[0]!
    day.blocks[0]!.exercises[1]!.restSeconds = 75

    draft.removeExercise(day, 0, 1)
    expect(day.blocks[0]!.exercises.map(exercise => exercise.restSeconds)).toEqual([75])

    draft.removeExercise(day, 0, 0)
    expect(day.blocks, 'an emptied block goes').toEqual([])
  })

  it('keeps the rest of the round when an exercise before the last is removed', () => {
    const draft = useProgramDraft()
    draft.add(bench, 0, null)
    draft.add(raise, 0, 0)
    draft.add(plank, 0, 0)
    const day = draft.days.value[0]!
    day.blocks[0]!.exercises[2]!.restSeconds = 90

    draft.removeExercise(day, 0, 0)
    expect(day.blocks[0]!.exercises.map(exercise => exercise.restSeconds)).toEqual([0, 90])
  })

  it('names new days in order, moves blocks and removes days', () => {
    const draft = useProgramDraft()
    expect(draft.addDay().name).toBe('Jour B')
    draft.add(bench, 0, null)
    draft.add(raise, 0, null)
    const day = draft.days.value[0]!

    expect(draft.moveBlock(day, 1, -1)?.exercises[0]?.name).toBe(raise.name)
    expect(day.blocks.map(block => block.exercises[0]!.name)).toEqual([raise.name, bench.name])

    draft.removeDay(1)
    expect(draft.days.value).toHaveLength(1)
  })

  it('sends blanks as missing, never as 0', () => {
    const draft = useProgramDraft()
    draft.name.value = 'Upper'
    draft.add(bench, 0, null)
    Object.assign(draft.days.value[0]!.blocks[0]!.exercises[0]!, { sets: '', repMin: '6', incrementKg: '1,25', warmupSets: '', tempo: ' ', notes: ' Coudes ' })

    expect(draft.body().days?.[0]?.blocks?.[0]?.exercises?.[0]).toMatchObject({ sets: undefined, repMin: 6, incrementKg: 1.25, warmupSets: 0, tempo: null, notes: 'Coudes' })
  })
})
