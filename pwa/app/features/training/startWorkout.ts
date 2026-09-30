import { messageFor } from '~/services/api/messages'
import { planFor } from './plans'
import { useWorkoutSession } from './session'
import type { Program } from './training'

/**
 * Starts a workout and opens it: a day of a program (its plan from the API, or as the device kept
 * it without signal), or a free one. Where it was started from (the training page, the dashboard)
 * shows the failure.
 */
export function useStartWorkout() {
  const api = useApi()
  const session = useWorkoutSession()
  const submission = useSubmission()
  /** The day being started, to show which button is busy. */
  const starting = ref<number | 'free' | null>(null)

  const message = computed(() => {
    const failure = submission.error.value
    if (!failure) return null
    return ['network', 'server'].includes(failure.kind) ? 'Pas de connexion, et ce jour n’est pas encore gardé sur l’appareil.' : messageFor(failure)
  })

  async function start(program: Pick<Program, 'id' | 'currentVersion'> | null, day: number | 'free'): Promise<boolean> {
    starting.value = day
    try {
      return await submission.run(async () => {
        await session.start(day === 'free' || !program ? null : await planFor(api, program, day))
        await navigateTo('/training/seance')
      })
    }
    finally {
      starting.value = null
    }
  }

  return { start, starting: readonly(starting), pending: submission.pending, message }
}
