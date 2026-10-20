import type { Exercise, Program, ProgramExercise } from '~/features/training/training'
import type { WorkoutExercise, WorkoutSet } from '~/features/training/workout'

export const bench: Exercise = {
  id: '0199a0b1-0000-7000-8000-0000000000e1',
  source: 'library',
  name: 'Développé couché à la barre',
  primaryMuscle: 'chest',
  secondaryMuscles: ['triceps', 'front_delts'],
  type: 'compound',
  equipment: 'barbell',
  measurement: 'weight',
  description: null,
  mediaUrl: null,
  libraryKey: 'barbell_bench_press',
  repMin: 6,
  repMax: 10,
  targetRir: 2,
  restSeconds: 180,
  tempo: null,
  note: null,
  editable: false,
}

export const raise: Exercise = { ...bench, id: '0199a0b1-0000-7000-8000-0000000000e2', name: 'Élévations latérales aux haltères', libraryKey: 'lateral_raise', primaryMuscle: 'side_delts', secondaryMuscles: ['traps'], type: 'isolation', equipment: 'dumbbell', repMin: 12, repMax: 20, targetRir: 1, restSeconds: 60 }

export const plank: Exercise = { ...bench, id: '0199a0b1-0000-7000-8000-0000000000e3', name: 'Gainage (planche)', libraryKey: 'plank', primaryMuscle: 'abs', secondaryMuscles: ['obliques'], type: 'isolation', equipment: 'bodyweight', measurement: 'duration', repMin: 30, repMax: 60, targetRir: null, restSeconds: 60 }

export const yates: Exercise = { ...bench, id: '0199a0b1-0000-7000-8000-0000000000e9', source: 'custom', libraryKey: null, name: 'Rowing Yates', primaryMuscle: 'upper_back', secondaryMuscles: ['biceps'], editable: true, note: 'Buste à 30°', description: 'Prise supination', mediaUrl: 'https://example.org/yates', tempo: '2-0-1-1' }

export function planned(exercise: Exercise, override: Partial<ProgramExercise> = {}): ProgramExercise {
  return {
    exerciseId: exercise.id,
    name: exercise.name,
    measurement: exercise.measurement,
    available: true,
    sets: 3,
    warmupSets: 0,
    repMin: exercise.repMin,
    repMax: exercise.repMax,
    targetRir: exercise.targetRir,
    restSeconds: exercise.restSeconds,
    tempo: null,
    strategy: 'double_progression',
    incrementKg: 2.5,
    dropSet: false,
    notes: null,
    ...override,
  }
}

export const ppl: Program = {
  id: '0199a0b1-0000-7000-8000-00000000b001',
  name: 'PPL Masse',
  active: true,
  currentVersion: 2,
  version: 2,
  versionCreatedAt: '2026-09-20T10:00:00+00:00',
  days: [
    { name: 'Push', blocks: [
      { exercises: [planned(bench, { sets: 4, warmupSets: 2, tempo: '3-1-1-0', notes: 'Pause sur la poitrine', strategy: 'linear' })] },
      { exercises: [planned(raise), planned(plank, { dropSet: true })] },
    ] },
    { name: 'Pull', blocks: [{ exercises: [planned(yates, { available: false })] }] },
  ],
  versions: [{ number: 2, createdAt: '2026-09-20T10:00:00+00:00' }, { number: 1, createdAt: '2026-09-01T10:00:00+00:00' }],
}

/** An exercise as a workout (or its plan) holds it. */
export function workoutExercise(index: number, block: number, override: Partial<WorkoutExercise> = {}): WorkoutExercise {
  return {
    index, exerciseId: `0199a0b1-0000-7000-8000-00000000e${String(index).padStart(3, '0')}`, name: `Exercice ${index + 1}`, measurement: 'weight', block, added: false,
    sets: 2, warmupSets: 0, repMin: 8, repMax: 12, targetRir: 2, restSeconds: 90, tempo: null, strategy: 'double_progression',
    incrementKg: 2.5, dropSet: false, programNotes: null, note: null, previous: null, suggestion: null, records: [], newRecords: [],
    ...override,
  }
}

let sequence = 0
export function workoutSet(exerciseIndex: number, override: Partial<WorkoutSet> = {}): WorkoutSet {
  return { id: `0199a0b1-0000-7000-8000-${String(++sequence).padStart(12, '0')}`, exerciseIndex, kind: 'normal', loadKg: 80, reps: 8, seconds: null, rir: null, doneAt: '2026-09-28T18:00:00Z', ...override }
}
