import { download } from '~/services/download'
import type { AccountExport } from './exports'

/** Codes saying the export is no longer what the list shows (expired, gone): the list is read again. */
const GONE = new Set(['export.not_found', 'export.not_ready'])

/**
 * Downloading an export: a fresh signed link asked for (the server records it), then saved without
 * leaving the app.
 */
export function useExportDownload(refresh: () => Promise<unknown>) {
  const api = useApi()
  const downloading = ref<string | null>(null)
  const submission = useSubmission()

  async function fetchArchive(exported: AccountExport): Promise<void> {
    downloading.value = exported.id
    const done = await submission.run(async () => {
      const { url } = await api.post('/api/exports/{id}/download', undefined, { path: { id: exported.id } })
      download(url)
    })
    downloading.value = null
    if (!done && GONE.has(submission.error.value?.code ?? '')) void refresh()
  }

  return { downloading: readonly(downloading), message: submission.message, fetchArchive }
}
