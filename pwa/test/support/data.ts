import type { AccountExport, ExportDomain } from '~/features/data/exports'
import type { DataPolicy } from '~/features/data/policy'
import type { Activity } from '~/features/activity/activity'

export const DOMAINS: ExportDomain[] = [
  { key: 'identity', title: 'Compte', periodFiltered: false },
  { key: 'nutrition', title: 'Nutrition', periodFiltered: true },
  { key: 'training', title: 'Training', periodFiltered: true },
]

export const DATA_POLICY: DataPolicy = { deletionDelayDays: 7, exportAvailableHours: 24, activityRetentionDays: 365, reauthenticationMinutes: 10 }

export function accountExport(override: Partial<AccountExport> = {}): AccountExport {
  return {
    id: '0199a0b1-0000-7000-8000-00000000e001',
    status: 'queued',
    from: null,
    to: null,
    domains: [],
    requestedAt: '2026-09-30T08:00:00+00:00',
    readyAt: null,
    expiresAt: null,
    downloadable: false,
    ...override,
  } as AccountExport
}

export function activity(override: Partial<Activity> = {}): Activity {
  const occurredAt = override.occurredAt ?? '2026-09-30T08:00:00+00:00'
  return {
    id: '0199a0b1-0000-7000-8000-00000000a001',
    action: 'identity.signed_in',
    details: {},
    source: 'user',
    occurredAt,
    createdAt: occurredAt,
    ...override,
  } as Activity
}
