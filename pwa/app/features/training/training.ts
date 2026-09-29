import type { components } from '~/types/api'

export type Exercise = components['schemas']['Exercise']
export type ExerciseInput = components['schemas']['Exercise.ExerciseInput']
export type Program = components['schemas']['Program']
export type ProgramInput = components['schemas']['Program.ProgramInput']
export type ProgramDay = components['schemas']['ProgramDay']
export type ProgramBlock = components['schemas']['ProgramBlock']
export type ProgramExercise = components['schemas']['ProgramExercise']
export type PlannedExerciseInput = components['schemas']['PlannedExerciseInput']

export type Muscle = Exercise['primaryMuscle']
export type Equipment = Exercise['equipment']
export type ExerciseType = Exercise['type']
export type Measurement = Exercise['measurement']
export type Strategy = ProgramExercise['strategy']

type Option<T extends string> = { value: T, label: string }

export const MUSCLES: ReadonlyArray<Option<Muscle>> = [
  { value: 'chest', label: 'Pectoraux' },
  { value: 'lats', label: 'Grand dorsal' },
  { value: 'upper_back', label: 'Haut du dos' },
  { value: 'traps', label: 'Trapèzes' },
  { value: 'lower_back', label: 'Lombaires' },
  { value: 'front_delts', label: 'Deltoïdes antérieurs' },
  { value: 'side_delts', label: 'Deltoïdes latéraux' },
  { value: 'rear_delts', label: 'Deltoïdes postérieurs' },
  { value: 'biceps', label: 'Biceps' },
  { value: 'triceps', label: 'Triceps' },
  { value: 'forearms', label: 'Avant-bras' },
  { value: 'abs', label: 'Abdominaux' },
  { value: 'obliques', label: 'Obliques' },
  { value: 'glutes', label: 'Fessiers' },
  { value: 'quads', label: 'Quadriceps' },
  { value: 'hamstrings', label: 'Ischio-jambiers' },
  { value: 'adductors', label: 'Adducteurs' },
  { value: 'calves', label: 'Mollets' },
]

export const EQUIPMENT: ReadonlyArray<Option<Equipment>> = [
  { value: 'barbell', label: 'Barre' },
  { value: 'dumbbell', label: 'Haltères' },
  { value: 'ez_bar', label: 'Barre EZ' },
  { value: 'machine', label: 'Machine' },
  { value: 'cable', label: 'Poulie' },
  { value: 'smith_machine', label: 'Smith machine' },
  { value: 'kettlebell', label: 'Kettlebell' },
  { value: 'band', label: 'Élastique' },
  { value: 'bodyweight', label: 'Poids du corps' },
  { value: 'other', label: 'Autre' },
]

export const EXERCISE_TYPES: ReadonlyArray<Option<ExerciseType>> = [
  { value: 'compound', label: 'Polyarticulaire' },
  { value: 'isolation', label: 'Isolation' },
]

export const MEASUREMENTS: ReadonlyArray<Option<Measurement>> = [
  { value: 'weight', label: 'Charge et répétitions' },
  { value: 'bodyweight', label: 'Poids du corps (lest facultatif)' },
  { value: 'duration', label: 'Durée' },
]

export const STRATEGIES: ReadonlyArray<Option<Strategy> & { description: string }> = [
  { value: 'double_progression', label: 'Double progression', description: 'Les reps montent dans la fourchette, puis la charge.' },
  { value: 'linear', label: 'Progression linéaire', description: 'Plus de charge dès que toutes les séries atteignent le bas de la fourchette.' },
  { value: 'fixed_load', label: 'Charge fixe', description: 'Même charge\u00A0: seules les reps progressent.' },
  { value: 'fixed_reps', label: 'Reps fixes', description: 'Mêmes reps\u00A0: la charge monte quand les séries deviennent faciles.' },
  { value: 'target_rir', label: 'RIR cible', description: 'La charge suit l’effort ressenti.' },
]

export function labelOf<T extends string>(options: ReadonlyArray<Option<T>>, value: T): string {
  return options.find(option => option.value === value)?.label ?? value
}

/** "8–12 reps", "5 reps", "30–60 s". */
export function formatTarget(min: number, max: number, measurement: Measurement): string {
  const range = min === max ? `${min}` : `${min}–${max}`
  return measurement === 'duration' ? `${range}\u00A0s` : `${range}\u00A0reps`
}

/** "1 min 30", "45 s", "sans repos". */
export function formatRest(seconds: number): string {
  if (seconds <= 0) return 'sans repos'
  const minutes = Math.floor(seconds / 60)
  const rest = seconds % 60
  if (minutes === 0) return `${rest}\u00A0s`
  return rest === 0 ? `${minutes}\u00A0min` : `${minutes}\u00A0min\u00A0${String(rest).padStart(2, '0')}`
}

/**
 * Position of an exercise in its day, as gyms write it: A, B, C… for each block, A1, A2 for the
 * exercises of a superset.
 */
export function blockLabel(blockIndex: number, exerciseIndex: number, blockSize: number): string {
  return blockSize > 1 ? `${blockLetter(blockIndex)}${exerciseIndex + 1}` : blockLetter(blockIndex)
}

type Prescribed = Pick<ProgramExercise, 'sets' | 'repMin' | 'repMax' | 'measurement' | 'targetRir' | 'restSeconds' | 'tempo'>

/** "3 × 8–12 reps · RIR 2 · repos 2 min" (and the tempo when asked). */
export function prescription(exercise: Prescribed, { rest = true, tempo = false } = {}): string {
  return [
    `${exercise.sets}\u00A0×\u00A0${formatTarget(exercise.repMin, exercise.repMax, exercise.measurement)}`,
    exercise.targetRir === null ? null : `RIR\u00A0${exercise.targetRir}`,
    rest ? `repos ${formatRest(exercise.restSeconds)}` : null,
    tempo && exercise.tempo ? `tempo ${exercise.tempo}` : null,
  ].filter(Boolean).join(' · ')
}

/** Sets and load step for an exercise no program prescribes: the API's PlannedExercise::usual(). */
export const USUAL_SETS = 3
export const USUAL_INCREMENT_KG = 2.5

/** An exercise as it is usually done, when it is added to a program or to a workout. */
export function plannedFrom(exercise: Exercise) {
  return {
    exerciseId: exercise.id,
    sets: USUAL_SETS,
    warmupSets: 0,
    repMin: exercise.repMin,
    repMax: exercise.repMax,
    targetRir: exercise.targetRir,
    restSeconds: exercise.restSeconds,
    tempo: exercise.tempo,
    strategy: 'double_progression' as Strategy,
    incrementKg: USUAL_INCREMENT_KG,
    dropSet: false,
  }
}

/** A, B, C… */
export function blockLetter(index: number): string {
  return String.fromCharCode(65 + (index % 26))
}
