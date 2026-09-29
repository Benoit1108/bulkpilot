import type { Program } from '~/features/training/training'
import type { WorkoutPlan } from '~/features/training/workout'
import type { ApiClient } from '~/services/api/client'
import { asApiError } from '~/services/api/client'
import type { ApiError } from '~/services/api/errors'
import { offlineStore } from '~/services/offline/store'

/**
 * What a workout needs to start without signal (spec §116): the program followed and the plan of
 * each of its days, kept on the device whenever the API answers.
 */
const ACTIVE = 'training.activeProgram'
const planKey = (programId: string, version: number, day: number) => `training.plan.${programId}.${version}.${day}`

/** Worth falling back to what the device kept: the API cannot be reached, or is down. */
function unreachable(error: ApiError): boolean {
  return error.kind === 'network' || error.kind === 'server'
}

/**
 * The plan of a day of the program's current version, from the API when it answers (then kept),
 * from the device otherwise.
 *
 * @throws the API error when it cannot be reached and nothing was kept
 */
export async function planFor(api: ApiClient, program: Pick<Program, 'id' | 'currentVersion'>, day: number): Promise<WorkoutPlan> {
  const store = await offlineStore()
  try {
    const plan = await api.get('/api/workouts/preview', { query: { programId: program.id, day } })
    await store.set(planKey(program.id, plan.programDay.versionNumber, day), plan)
    return plan
  }
  catch (caught) {
    const error = asApiError(caught)
    const kept = unreachable(error) ? await store.get<WorkoutPlan>(planKey(program.id, program.currentVersion, day)) : undefined
    if (kept) return kept
    throw error
  }
}

/** The program followed, kept for a start without signal. */
export async function keepActiveProgram(program: Program | null): Promise<void> {
  const store = await offlineStore()
  if (program) await store.set(ACTIVE, program)
  else await store.remove(ACTIVE)
}

export async function keptActiveProgram(): Promise<Program | null> {
  return await (await offlineStore()).get<Program>(ACTIVE) ?? null
}

/** Keeps every day of the program on the device, ahead of time (quietly: failures are ignored). */
export async function keepPlans(api: ApiClient, program: Program): Promise<void> {
  await keepActiveProgram(program)
  await Promise.allSettled(program.days.map((_, day) => planFor(api, program, day)))
}
