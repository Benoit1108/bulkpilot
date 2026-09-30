import { COACH_KEYS, type Coach } from './coach'

/**
 * The Coach of the user's today (the Coach page and the dashboard share its key): its analysis
 * runs once a day, so whichever opens first runs it and the other finds it done. The history and
 * the reports may have grown with it.
 */
export function useCoachOfDay(today: () => string) {
  const api = useApi()

  async function analyse(again: boolean): Promise<Coach> {
    const coach = await api.post('/api/coach/analysis', { today: today(), again })
    clearNuxtData(key => key === COACH_KEYS.history || key === COACH_KEYS.reports)
    return coach
  }

  const { data: coach, status, error, refresh } = useAsyncData(() => COACH_KEYS.coach(today()), () => analyse(false))
  return { coach, status, error, refresh, analyse }
}
