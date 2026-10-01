<script setup lang="ts">
import { EXPORT_STATUS_LABELS, type AccountExport, type ExportDomain } from '../exports'
import { exportWhen, requestedText, scopeText } from '../format'

/** The recent exports, the latest first, each with its state and, once ready, its download. */
defineProps<{ exports: readonly AccountExport[], domains: readonly ExportDomain[], downloading: string | null }>()
const emit = defineEmits<{ download: [exported: AccountExport] }>()
</script>

<template>
  <ul
    class="m-0 flex list-none flex-col p-0"
    role="list"
    aria-label="Exports récents, le plus récent d’abord"
  >
    <li
      v-for="exported in exports"
      :key="exported.id"
      class="flex flex-wrap items-center justify-between gap-3 border-line py-3 not-first:border-t"
    >
      <div class="flex min-w-0 flex-col gap-1">
        <p class="font-semibold">
          {{ EXPORT_STATUS_LABELS[exported.status] }}
          <span class="font-normal text-muted">· {{ exportWhen(exported) }}</span>
        </p>
        <p class="text-sm text-muted">
          {{ scopeText(exported, domains) }}
        </p>
        <p
          v-if="exported.status === 'failed'"
          class="text-sm text-danger"
        >
          La préparation a échoué&nbsp;: demande un nouvel export.
        </p>
      </div>
      <UiButton
        v-if="exported.downloadable"
        variant="secondary"
        :loading="downloading === exported.id"
        @click="emit('download', exported)"
      >
        Télécharger<span class="sr-only"> l’export {{ requestedText(exported) }}</span>
      </UiButton>
    </li>
  </ul>
</template>
