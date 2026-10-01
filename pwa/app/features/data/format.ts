import { formatFullDay, momentLabel } from '~/services/calendar/days'
import type { AccountExport, ExportDomain } from './exports'

/** « Du 1 septembre 2026 au 30 septembre 2026 », « Depuis le … », « Toute la période ». */
export function periodText(from: string | null | undefined, to: string | null | undefined): string {
  if (from && to) return `Du ${formatFullDay(from)} au ${formatFullDay(to)}`
  if (from) return `Depuis le ${formatFullDay(from)}`
  return to ? `Jusqu’au ${formatFullDay(to)}` : 'Toute la période'
}

/** « Toute la période · toutes les données », « Du … au … · Nutrition, Training ». */
export function scopeText(exported: AccountExport, domains: readonly ExportDomain[]): string {
  const { from, to, domains: keys } = exported
  const period = periodText(from, to)
  const titles = keys.length === 0 ? 'toutes les données' : keys.map(key => domains.find(domain => domain.key === key)?.title ?? key).join(', ')
  return `${period} · ${titles}`
}

/** « demandé aujourd’hui à 09:58 ». */
export const requestedText = (exported: AccountExport, now = new Date()) => `demandé ${momentLabel(exported.requestedAt, now)}`

/** « Disponible jusqu’à demain à 10:05 » once ready, « Demandé aujourd’hui à 09:58 » before. */
export function exportWhen(exported: AccountExport, now = new Date()): string {
  if (exported.status === 'ready' && exported.expiresAt) return `Disponible jusqu’à ${momentLabel(exported.expiresAt, now)}`
  return `Demandé ${momentLabel(exported.requestedAt, now)}`
}
