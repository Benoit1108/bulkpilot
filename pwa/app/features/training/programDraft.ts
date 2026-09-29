import { blockLetter, plannedFrom, type Exercise, type Measurement, type PlannedExerciseInput, type Program, type ProgramInput, type Strategy } from './training'
import { parseAmount, wholeAmount, type Amount } from '~/services/text/numbers'

export const MAX_DAYS = 7 // ProgramVersion::MAX_DAYS
export const MAX_BLOCKS = 15 // TrainingDay::MAX_BLOCKS
export const MAX_IN_BLOCK = 4 // ExerciseBlock::MAX_EXERCISES

export interface PlannedRow {
  row: number
  exerciseId: string
  name: string
  measurement: Measurement
  available: boolean
  sets: Amount
  repMin: Amount
  repMax: Amount
  targetRir: Amount
  restSeconds: Amount
  strategy: Strategy | null
  incrementKg: Amount
  warmupSets: Amount
  dropSet: boolean
  tempo: string
  notes: string
}
export interface BlockRow { row: number, exercises: PlannedRow[] }
export interface DayRow { row: number, name: string, blocks: BlockRow[] }

function planned(row: PlannedRow): PlannedExerciseInput {
  return {
    exerciseId: row.exerciseId,
    sets: wholeAmount(row.sets),
    repMin: wholeAmount(row.repMin),
    repMax: wholeAmount(row.repMax),
    targetRir: wholeAmount(row.targetRir) ?? null,
    restSeconds: wholeAmount(row.restSeconds),
    strategy: row.strategy ?? 'double_progression',
    incrementKg: parseAmount(row.incrementKg) ?? undefined,
    warmupSets: wholeAmount(row.warmupSets) ?? 0,
    dropSet: row.dropSet,
    tempo: row.tempo.trim() || null,
    notes: row.notes.trim() || null,
  } as PlannedExerciseInput
}

/**
 * A program being edited: days of blocks of exercises, a block of several being a superset.
 * Each row has a stable `row` key, so the list keeps its focus and state through moves.
 */
export function useProgramDraft(program?: Program | null) {
  let nextRow = 0
  const name = ref(program?.name ?? '')
  const days = ref<DayRow[]>(program?.days.map(day => ({
    row: nextRow++,
    name: day.name,
    blocks: day.blocks.map(block => ({
      row: nextRow++,
      exercises: block.exercises.map(exercise => ({ row: nextRow++, ...exercise, tempo: exercise.tempo ?? '', notes: exercise.notes ?? '' })),
    })),
  })) ?? [{ row: nextRow++, name: 'Jour A', blocks: [] }])

  function fromExercise(exercise: Exercise): PlannedRow {
    return { row: nextRow++, name: exercise.name, measurement: exercise.measurement, available: true, ...plannedFrom(exercise), tempo: exercise.tempo ?? '', notes: '' }
  }

  return {
    name,
    days,

    /** Adds the exercise to a new block of the day, or to an existing block (superset). */
    add(exercise: Exercise, dayIndex: number, blockIndex: number | null): void {
      const day = days.value[dayIndex]
      if (!day) return
      const block = blockIndex === null ? undefined : day.blocks[blockIndex]
      if (!block) {
        day.blocks.push({ row: nextRow++, exercises: [fromExercise(exercise)] })
        return
      }
      // A superset rests after its last exercise: the ones before it go on without rest.
      block.exercises.forEach(member => (member.restSeconds = 0))
      block.exercises.push(fromExercise(exercise))
    },

    addDay(): DayRow {
      const day = { row: nextRow++, name: `Jour ${blockLetter(days.value.length)}`, blocks: [] }
      days.value.push(day)
      return day
    },

    removeDay(index: number): void {
      days.value.splice(index, 1)
    },

    moveBlock(day: DayRow, index: number, by: -1 | 1): BlockRow | undefined {
      const [block] = day.blocks.splice(index, 1)
      if (block) day.blocks.splice(index + by, 0, block)
      return block
    },

    removeExercise(day: DayRow, blockIndex: number, exerciseIndex: number): void {
      const block = day.blocks[blockIndex]
      if (!block) return
      const [removed] = block.exercises.splice(exerciseIndex, 1)
      const last = block.exercises.at(-1)
      // The round's rest lives on the last exercise: it passes to the one now last.
      if (removed && last && exerciseIndex === block.exercises.length) last.restSeconds = removed.restSeconds
      if (!block.exercises.length) day.blocks.splice(blockIndex, 1)
    },

    body(): ProgramInput {
      return {
        name: name.value,
        days: days.value.map(day => ({ name: day.name, blocks: day.blocks.map(block => ({ exercises: block.exercises.map(planned) })) })),
      }
    },
  }
}
