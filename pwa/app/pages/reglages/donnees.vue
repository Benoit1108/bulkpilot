<script setup lang="ts">
import ExportForm from '~/features/data/components/ExportForm.vue'
import ExportList from '~/features/data/components/ExportList.vue'
import { EXPORT_KEYS, isUnderWay, useExportDomains, type AccountExport } from '~/features/data/exports'
import { useExportDownload } from '~/features/data/useExportDownload'

definePageMeta({ title: 'Mes données' })

const api = useApi()
const { data: exports, error, refresh } = useAsyncData(EXPORT_KEYS.list, () => api.get('/api/exports'))
const { data: domains } = useExportDomains()
const notice = ref<string | null>(null)

// While one is being made, the list is read again now and then — more slowly as time goes, not
// while the page is hidden, and also after a failed read; what it became is announced.
const underWay = computed(() => (exports.value ?? []).some(exported => isUnderWay(exported.status)))
// A failed read leaves no list: it goes on as long as the last one read had an export under way.
const lastUnderWay = ref(false)
watch(exports, (list) => {
  if (list) lastUnderWay.value = underWay.value
}, { immediate: true })
const polling = usePolling(() => (exports.value ? underWay.value : lastUnderWay.value), async () => {
  await refresh()
  if (error.value) throw error.value
})
watch(exports, (now, before) => {
  polling.schedule()
  for (const exported of now ?? []) {
    const was = before?.find(one => one.id === exported.id)
    if (!was || !isUnderWay(was.status)) continue
    if (exported.status === 'ready') notice.value = 'Ton export est prêt\u00A0: tu peux le télécharger.'
    if (exported.status === 'failed') notice.value = 'La préparation de ton export a échoué.'
  }
}, { immediate: true })

function requested(exported: AccountExport): void {
  exports.value = [exported, ...(exports.value ?? []).filter(one => one.id !== exported.id)]
  notice.value = 'Export demandé\u00A0: il se prépare, tu peux quitter cette page.'
  polling.restart()
}

const { downloading, message: downloadError, fetchArchive } = useExportDownload(refresh)
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/reglages"
      label="Réglages"
    />
    <UiCard title="Exporter mes données">
      <ExportForm
        :under-way="underWay"
        @requested="requested"
      />
    </UiCard>
    <UiCard title="Exports récents">
      <UiAlert
        v-if="downloadError"
        tone="error"
      >
        {{ downloadError }}
      </UiAlert>
      <UiLoadError
        v-if="error && !exports"
        @retry="refresh()"
      />
      <ExportList
        v-else-if="exports?.length"
        :exports="exports"
        :domains="domains ?? []"
        :downloading="downloading"
        @download="fetchArchive"
      />
      <p
        v-else-if="exports"
        class="text-muted"
      >
        Aucun export pour l’instant.
      </p>
      <p
        v-else
        role="status"
        class="min-h-28 text-muted"
      >
        Chargement…
      </p>
    </UiCard>
    <p class="text-sm text-muted">
      Tu veux partir&#x202F;? Tu peux aussi <NuxtLink
        to="/reglages/suppression"
        class="font-semibold text-accent-ink"
      >supprimer ton compte</NuxtLink>.
    </p>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
</template>
