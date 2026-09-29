import type { SetValues } from './session'
import { blockLetter, formatRest } from './training'
import { isWorking, type SetKind, type WorkoutExercise, type WorkoutSet } from './workout'

/**
 * Where the values of the next set start: the same kind of set done just before, else (for
 * work) the suggestion or last time; a warm-up at half the working load; the drop set a quarter
 * lighter than the last working set.
 */
export function startingValues(exercise: WorkoutExercise, own: readonly WorkoutSet[], kind: SetKind): Partial<SetValues> {
  const target = exercise.suggestion ?? exercise.previous?.sets.find(isWorking) ?? null
  const lastWorking = own.filter(isWorking).at(-1)
  const timed = exercise.measurement === 'duration'
  const round = (value: number) => Math.max(0, Math.round(value / exercise.incrementKg) * exercise.incrementKg)
  if (kind === 'drop' && lastWorking) return { kind: 'drop', loadKg: round((lastWorking.loadKg ?? 0) * 0.75), reps: lastWorking.reps, seconds: lastWorking.seconds }
  if (kind === 'warmup') {
    const lastWarmup = own.filter(set => set.kind === 'warmup').at(-1)
    if (lastWarmup) return { kind: 'warmup', loadKg: lastWarmup.loadKg, reps: lastWarmup.reps, seconds: lastWarmup.seconds }
    return { kind: 'warmup', loadKg: target?.loadKg ? round(target.loadKg / 2) : null, reps: timed ? null : exercise.repMax, seconds: timed ? exercise.repMin : null }
  }
  if (lastWorking) return { kind: 'normal', loadKg: lastWorking.loadKg, reps: lastWorking.reps, seconds: lastWorking.seconds, rir: lastWorking.rir }
  if (target) return { kind: 'normal', loadKg: target.loadKg, reps: target.reps, seconds: target.seconds }
  return { kind: 'normal', reps: timed ? null : exercise.repMax, seconds: timed ? exercise.repMin : null }
}

/** Where the exercise sits: "Superset A · 1/2 — ensuite : Élévations latérales (sans repos)". */
export function supersetLine(exercise: WorkoutExercise, all: readonly WorkoutExercise[]): string | null {
  const block = all.filter(other => other.block === exercise.block)
  if (block.length < 2) return null
  const position = block.findIndex(other => other.index === exercise.index)
  const letter = blockLetter([...new Set(all.map(other => other.block))].indexOf(exercise.block))
  const then = block[position + 1]
  return `Superset ${letter} · ${position + 1}/${block.length}\u00A0— ${then ? `ensuite\u00A0: ${then.name} (sans repos)` : `puis repos\u00A0: ${formatRest(exercise.restSeconds)}`}`
}
