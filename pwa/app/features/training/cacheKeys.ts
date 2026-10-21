/**
 * useAsyncData keys shared by several pages, so that a write can invalidate what it changed.
 */
export const TRAINING_KEYS = {
  exercises: 'training-exercises',
  programs: 'training-programs',
  history: 'training-history',
  exercise: (id: string) => `training-exercise-${id}`,
  program: (id: string, version: number | null) => `training-program-${id}-${version ?? 'current'}`,
  workout: (id: string) => `training-workout-${id}`,
  nextWorkout: 'training-next-workout',
  analytics: (period: string, today: string) => `training-analytics-${period}-${today}`,
  /** The week under way and the last full one. */
  week: (today: string) => `training-week-${today}`,
  /** The workouts of a month, by its first day. */
  month: (first: string) => `training-month-${first}`,
} as const

/** The finished workouts read at a time (the history's pages; its first one is shared with the search). */
export const HISTORY_PAGE = 20

/** Everything a program change can affect: the list and every version shown. */
export function isProgramKey(key: string): boolean {
  return key === TRAINING_KEYS.programs || key.startsWith('training-program-')
}

/** Exercise names and notes appear in programs too. */
export function isExerciseKey(key: string): boolean {
  return key === TRAINING_KEYS.exercises || key.startsWith('training-exercise-') || isProgramKey(key)
}
