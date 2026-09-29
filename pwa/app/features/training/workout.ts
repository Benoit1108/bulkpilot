import type { components } from '~/types/api'

export type Workout = components['schemas']['Workout']
export type WorkoutPlan = components['schemas']['WorkoutPlan']
export type WorkoutExercise = components['schemas']['WorkoutExerciseOutput']
export type WorkoutSet = components['schemas']['WorkoutSetOutput']
export type WorkoutSummary = components['schemas']['Workout.WorkoutSummary']
export type Suggestion = components['schemas']['SuggestionOutput']
export type PersonalRecord = components['schemas']['RecordOutput']
export type ExerciseProgress = components['schemas']['ExerciseProgress']
export type ProgramDayRef = components['schemas']['WorkoutProgramDay']
export type SetKind = WorkoutSet['kind']

export const SET_KINDS: ReadonlyArray<{ value: SetKind, label: string, hint: string }> = [
  { value: 'warmup', label: 'Échauffement', hint: 'Pour se préparer, ne compte pas.' },
  { value: 'normal', label: 'Travail', hint: 'Une série prévue par le programme.' },
  { value: 'drop', label: 'Drop set', hint: 'Plus léger, juste après la série, sans repos.' },
  { value: 'approach', label: 'Approche', hint: 'Montée en charge avant la 1ʳᵉ série de travail, ne compte pas.' },
  { value: 'effective', label: 'Effective', hint: 'Série de travail menée près de l’échec.' },
]

/** On one tap in training mode; the other kinds are one tap further. */
export const MAIN_KINDS: readonly SetKind[] = ['warmup', 'normal', 'drop']

/** Working sets are what the program counts (the API's SetKind::isWorking(), for progression and records). */
export const WORKING_KINDS: readonly SetKind[] = ['normal', 'effective']

export const isWorking = (set: Pick<WorkoutSet, 'kind'>) => WORKING_KINDS.includes(set.kind)

/** After a warm-up, a short rest whatever the exercise's. */
export const WARMUP_REST_MAX_S = 90

const RECORDS: Record<PersonalRecord['kind'], string> = {
  load: 'charge max',
  estimated_one_rep_max: '1RM estimé',
  volume: 'volume de la séance',
  reps: 'répétitions',
  duration: 'durée',
  reps_at_load: 'répétitions à cette charge',
}

const SUGGESTIONS: Record<Suggestion['reason'], string> = {
  increase_load: 'toutes les séries au maximum la dernière fois',
  add_reps: 'une répétition de plus',
  repeat: 'objectif pas encore atteint',
  decrease_load: 'séries plus dures que prévu',
}

const kg = (value: number) => `${value.toLocaleString('fr-FR', { maximumFractionDigits: 2 })}\u00A0kg`

/** "80 kg × 8 · RIR 2", "+10 kg × 12", "12 reps", "45 s". */
export function formatSet(set: Pick<WorkoutSet, 'loadKg' | 'reps' | 'seconds' | 'rir'>, measurement: WorkoutExercise['measurement']): string {
  let main: string
  if (measurement === 'duration') main = `${set.seconds ?? 0}\u00A0s`
  else if (measurement === 'bodyweight') main = set.loadKg ? `+${kg(set.loadKg)} × ${set.reps ?? 0}` : `${set.reps ?? 0}\u00A0reps`
  else main = `${kg(set.loadKg ?? 0)} × ${set.reps ?? 0}`
  return set.rir === null ? main : `${main} · RIR\u00A0${set.rir}`
}

export function formatRecord(record: PersonalRecord, measurement: WorkoutExercise['measurement']): string {
  const value = record.kind === 'reps' || record.kind === 'reps_at_load'
    ? `${record.value}\u00A0reps${record.loadKg ? ` à ${kg(record.loadKg)}` : ''}`
    : record.kind === 'duration' || measurement === 'duration' ? `${record.value}\u00A0s` : kg(record.value)
  return `${RECORDS[record.kind]}\u00A0: ${value}`
}

export function formatSuggestion(suggestion: Suggestion, exercise: WorkoutExercise): string {
  const target = suggestion.seconds !== null
    ? `${suggestion.seconds}\u00A0s`
    : exercise.measurement === 'bodyweight' && !suggestion.loadKg
      ? `${suggestion.reps ?? exercise.repMin}\u00A0reps`
      : `${exercise.measurement === 'bodyweight' ? '+' : ''}${kg(suggestion.loadKg ?? 0)} × ${suggestion.reps ?? exercise.repMin}`
  return `${target} (${SUGGESTIONS[suggestion.reason]})`
}

/** "00:42", "1:05:09" */
export function formatClock(milliseconds: number): string {
  const total = Math.max(0, Math.round(milliseconds / 1000))
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor((total % 3600) / 60)
  const seconds = total % 60
  const pad = (value: number) => String(value).padStart(2, '0')
  return hours > 0 ? `${hours}:${pad(minutes)}:${pad(seconds)}` : `${pad(minutes)}:${pad(seconds)}`
}

/** "1 min 30 s", for screen readers (a clock reads as punctuation). */
export function spokenDuration(milliseconds: number): string {
  const total = Math.max(0, Math.round(milliseconds / 1000))
  const hours = Math.floor(total / 3600)
  const minutes = Math.floor((total % 3600) / 60)
  const seconds = total % 60
  return [hours ? `${hours}\u00A0h` : null, minutes ? `${minutes}\u00A0min` : null, seconds || total === 0 ? `${seconds}\u00A0s` : null].filter(Boolean).join(' ')
}

/** How long a finished workout lasted, null while it goes on. */
export function workoutDuration(workout: { startedAt: string, finishedAt: string | null }): number | null {
  return workout.finishedAt ? new Date(workout.finishedAt).getTime() - new Date(workout.startedAt).getTime() : null
}

export function workingSetsOf(sets: readonly WorkoutSet[], exerciseIndex: number): WorkoutSet[] {
  return sets.filter(set => set.exerciseIndex === exerciseIndex && isWorking(set))
}

/** The program plans a drop set after the last working set, and it is not done yet. */
export function dropSetDue(exercise: WorkoutExercise, sets: readonly WorkoutSet[]): boolean {
  return exercise.dropSet
    && workingSetsOf(sets, exercise.index).length >= exercise.sets
    && !sets.some(set => set.exerciseIndex === exercise.index && set.kind === 'drop')
}

/** What the next set of this exercise most likely is: a planned warm-up, the planned drop set, or work. */
export function nextKind(exercise: WorkoutExercise, sets: readonly WorkoutSet[]): SetKind {
  const own = sets.filter(set => set.exerciseIndex === exercise.index)
  if (!own.some(isWorking) && own.filter(set => set.kind === 'warmup').length < exercise.warmupSets) return 'warmup'
  return dropSetDue(exercise, sets) ? 'drop' : 'normal'
}

/**
 * Where to go after a set, and how long to rest (spec §35, §44): through a superset without rest
 * (A1 → A2), resting after the round (the last exercise's rest), then to the next block once
 * every exercise of the block has its working sets. A warm-up keeps the user on the exercise with
 * a short rest; the planned drop set comes right after the last working set, without rest.
 */
export function afterSet(exercises: readonly WorkoutExercise[], sets: readonly WorkoutSet[], index: number, kind: SetKind): { next: number, restSeconds: number } {
  const current = exercises[index]
  if (!current || kind === 'drop') return { next: index, restSeconds: 0 }
  if (!WORKING_KINDS.includes(kind)) return { next: index, restSeconds: Math.min(current.restSeconds, WARMUP_REST_MAX_S) }
  if (dropSetDue(current, sets)) return { next: index, restSeconds: 0 }

  const block = exercises.filter(exercise => exercise.block === current.block)
  const position = block.findIndex(exercise => exercise.index === index)
  const done = (exercise: WorkoutExercise) => workingSetsOf(sets, exercise.index).length >= exercise.sets
  const roundRest = block.at(-1)!.restSeconds

  const following = block.slice(position + 1).find(exercise => !done(exercise))
  if (following) return { next: following.index, restSeconds: 0 }
  const again = block.find(exercise => !done(exercise))
  if (again) return { next: again.index, restSeconds: roundRest }
  const later = exercises.find(exercise => exercise.index > index && exercise.block !== current.block && !done(exercise))
  return { next: later?.index ?? index, restSeconds: roundRest }
}

/** Exercises with fewer working sets than planned: the user confirms before finishing (spec §46). */
export function unfinished(exercises: readonly WorkoutExercise[], sets: readonly WorkoutSet[]): WorkoutExercise[] {
  return exercises.filter(exercise => workingSetsOf(sets, exercise.index).length < exercise.sets)
}
