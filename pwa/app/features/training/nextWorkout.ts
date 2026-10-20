import type { ApiClient } from '~/services/api/client'
import { orNull } from '~/services/api/orNull'
import { TRAINING_KEYS } from './cacheKeys'

/** The day of the program followed that comes next (the server knows which); null without a program. */
export const fetchNextWorkout = (api: ApiClient) => orNull(() => api.get('/api/training/next-workout'))

export function useNextWorkout() {
  const api = useApi()
  return useAsyncData(TRAINING_KEYS.nextWorkout, () => fetchNextWorkout(api))
}
