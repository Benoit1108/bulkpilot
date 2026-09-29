import type { Ref } from 'vue'
import { useWorkoutSession } from './session'
import { formatRecord } from './workout'

/**
 * What only the API knows (records, suggestions), asked again after each write it received.
 * One request at a time; an answer older than the last one asked for is dropped. Each new record
 * is announced once, in `notice`.
 */
export function useWorkoutRecords(notice: Ref<string | null>): { refresh: () => Promise<void> } {
  const api = useApi()
  const session = useWorkoutSession()
  let asked = 0
  const announced = new Set<string>()

  async function refresh(): Promise<void> {
    const id = session.workout.value?.id
    if (!id) return
    const call = ++asked
    try {
      const server = await api.get('/api/workouts/{id}', { path: { id } })
      if (call !== asked) return
      await session.merge(server)
      const fresh = server.exercises.flatMap(exercise => exercise.newRecords.map(record => ({ exercise, record, key: `${exercise.index}-${record.kind}-${record.loadKg}-${record.value}` }))).filter(({ key }) => !announced.has(key))
      fresh.forEach(({ key }) => announced.add(key))
      if (fresh.length) {
        navigator.vibrate?.(100)
        notice.value = `Nouveau record\u00A0! ${fresh.map(({ exercise, record }) => `${exercise.name}\u00A0— ${formatRecord(record, exercise.measurement)}`).join(' ; ')}`
      }
    }
    catch {
      // Offline, or not received yet: the device carries on, records come later.
    }
  }

  return { refresh }
}
