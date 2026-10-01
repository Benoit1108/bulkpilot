import type { ResponseOf } from '~/services/api/contract'

export type AccountExport = ResponseOf<'/api/exports/{id}', 'get'>
export type ExportStatus = AccountExport['status']
export type ExportDomain = ResponseOf<'/api/exports/domains', 'get'>[number]

export const EXPORT_KEYS = {
  list: 'exports',
  domains: 'exports-domains',
} as const

export const EXPORT_STATUS_LABELS: Record<ExportStatus, string> = {
  queued: 'En préparation',
  processing: 'En préparation',
  ready: 'Prêt',
  failed: 'Échec',
  expired: 'Expiré',
}

/** Still being made: the list is read again until it is done, and no other one can be asked for. */
export const isUnderWay = (status: ExportStatus) => status === 'queued' || status === 'processing'

/** The domains one can export, shared by the form and the list (one request). */
export function useExportDomains() {
  const api = useApi()
  return useAsyncData(EXPORT_KEYS.domains, () => api.get('/api/exports/domains'))
}

/** What the archive holds, whatever is chosen (the server makes every format). */
export const EXPORT_CONTENTS = 'L’archive ZIP contient tes données en JSON, en CSV (un fichier par tableau, lisible dans un tableur), un classeur Excel, une synthèse PDF (non balisée pour les lecteurs d’écran\u00A0: les fichiers CSV et Excel en sont la version accessible) et tes photos de progression.'
