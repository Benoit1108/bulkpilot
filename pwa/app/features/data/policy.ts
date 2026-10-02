import type { ResponseOf } from '~/services/api/contract'
import { formatLongDay, localDay, shiftDay } from '~/services/calendar/days'
import { counted } from '~/services/text/plural'

/** The server's rules on personal data (delays, durations): shown as they are, never assumed. */
export type DataPolicy = ResponseOf<'/api/me/data-policy', 'get'>

export const DATA_POLICY_KEY = 'data-policy'

/** A policy is used only whole: an odd answer (a failing server) leaves the texts that say nothing of it. */
const complete = (policy: DataPolicy): DataPolicy | null =>
  [policy.deletionDelayDays, policy.exportAvailableHours, policy.activityRetentionDays, policy.reauthenticationMinutes]
    .every(value => typeof value === 'number' && Number.isFinite(value))
    ? policy
    : null

export function useDataPolicy() {
  const api = useApi()
  return useAsyncData(DATA_POLICY_KEY, async () => complete(await api.get('/api/me/data-policy')))
}

/** « le mercredi 7 octobre »: when an account closed today would be erased. */
export function erasureDay(policy: DataPolicy, now = new Date()): string {
  return `le ${formatLongDay(shiftDay(localDay(now), policy.deletionDelayDays))}`
}

/** « 7 jours », « un an » (365 days). */
export const daysText = (count: number) => (count === 365 ? 'un an' : counted(count, 'jour'))
/** « 24 heures ». */
export const hoursText = (count: number) => counted(count, 'heure')
