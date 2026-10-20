<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import ProgramForm from '~/features/training/components/ProgramForm.vue'
import ProgramSummary from '~/features/training/components/ProgramSummary.vue'
import { TRAINING_KEYS, isProgramKey } from '~/features/training/cacheKeys'
import type { Program } from '~/features/training/training'
import { asApiError } from '~/services/api/client'

definePageMeta({ title: 'Programme' })

const route = useRoute()
const id = computed(() => String(route.params.id))
// An older version lives in the URL (?version=2): back/forward and reloads keep it.
const version = computed(() => {
  const value = Number(route.query.version)
  return Number.isInteger(value) && value > 0 ? value : null
})
const api = useApi()
const key = computed(() => TRAINING_KEYS.program(id.value, version.value))
const { data: program, error, refresh } = useAsyncData(
  key,
  () => api.get('/api/programs/{id}', { path: { id: id.value }, query: version.value === null ? {} : { version: version.value } }),
)
usePageHeading(() => program.value?.name ?? null, { named: true })
const gone = computed(() => error.value !== null && error.value !== undefined && asApiError(error.value).kind === 'not_found')
const current = computed(() => program.value !== null && program.value !== undefined && program.value.version === program.value.currentVersion)
const hasHistory = computed(() => (program.value?.versions.length ?? 0) > 1)
const notice = ref<string | null>(null)
const action = useSubmission()
const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' })

/** Every other view of programs is stale; this page already shows the answer. */
function clearOthers(): void {
  clearNuxtData(cached => isProgramKey(cached) && cached !== key.value)
}

function onSaved(value: Program): void {
  const revised = program.value !== null && program.value !== undefined && value.currentVersion > program.value.currentVersion
  program.value = value
  clearOthers()
  notice.value = revised ? `Version ${value.currentVersion} enregistrée. Les séances passées gardent la leur.` : 'Programme enregistré.'
}

async function setActive(active: boolean): Promise<void> {
  await action.run(async () => {
    const path = { id: id.value }
    program.value = active ? await api.post('/api/programs/{id}/activate', undefined, { path }) : await api.post('/api/programs/{id}/deactivate', undefined, { path })
    clearOthers()
    notice.value = active ? 'C’est désormais le programme suivi.' : 'Plus aucun programme n’est suivi.'
  })
}

async function archive(): Promise<void> {
  if (await action.run(() => api.delete('/api/programs/{id}', { path: { id: id.value } }))) {
    clearNuxtData(isProgramKey)
    await navigateTo('/training/programmes')
  }
}
</script>

<template>
  <UiPage layout="columns">
    <template #top>
      <ShellBackLink
        to="/training/programmes"
        label="Mes programmes"
      />
    </template>
    <UiAlert
      v-if="gone"
      tone="error"
    >
      Ce programme (ou cette version) n’existe plus.
    </UiAlert>
    <UiLoadError
      v-else-if="error && !program"
      @retry="refresh()"
    />
    <template v-else-if="program">
      <UiCard>
        <p class="mb-3 text-sm text-muted">
          <template v-if="current">
            {{ program.active ? 'Programme suivi' : 'Programme non suivi' }} · version {{ program.version }}
          </template>
          <template v-else>
            Version {{ program.version }} du {{ dateFormat.format(new Date(program.versionCreatedAt)) }} (la version actuelle est la {{ program.currentVersion }})
          </template>
        </p>
        <NuxtLink
          v-if="!current"
          :to="`/training/programmes/${program.id}`"
          class="mb-2"
          :class="TEXT_LINK"
        >
          Revenir à la version actuelle
        </NuxtLink>
        <ProgramSummary :days="program.days" />
        <div
          v-if="current"
          class="mt-4 flex flex-wrap gap-2"
        >
          <UiButton
            v-if="!program.active"
            :loading="action.pending.value"
            @click="setActive(true)"
          >
            Suivre ce programme
          </UiButton>
          <UiButton
            v-else
            variant="secondary"
            :loading="action.pending.value"
            @click="setActive(false)"
          >
            Ne plus le suivre
          </UiButton>
        </div>
      </UiCard>
      <UiAlert
        v-if="action.message.value"
        tone="error"
      >
        {{ action.message.value }}
      </UiAlert>

      <UiCard
        v-if="hasHistory"
        title="Versions"
      >
        <p class="mb-3 text-sm text-muted">
          Chaque modification des jours crée une version&nbsp;; tes séances passées restent liées à la leur.
        </p>
        <ul
          class="flex flex-col"
          role="list"
        >
          <li
            v-for="past in program.versions"
            :key="past.number"
            class="flex min-h-tap items-center justify-between gap-3 border-t border-line"
          >
            <NuxtLink
              class="font-semibold text-accent-ink aria-[current=page]:text-inherit"
              :to="past.number === program.currentVersion ? `/training/programmes/${program.id}` : { query: { version: past.number } }"
              :aria-current="past.number === program.version ? 'page' : undefined"
            >
              Version {{ past.number }}<template v-if="past.number === program.currentVersion">
                (actuelle)
              </template>
            </NuxtLink>
            <span class="text-sm text-muted">{{ dateFormat.format(new Date(past.createdAt)) }}</span>
          </li>
        </ul>
      </UiCard>

      <template v-if="current">
        <UiCard title="Modifier">
          <ProgramForm
            :key="`${program.id}-${program.currentVersion}`"
            :program="program"
            @saved="onSaved"
          />
        </UiCard>
        <UiConfirmButton
          label="Archiver le programme"
          confirm-label="Confirmer l’archivage"
          :loading="action.pending.value"
          @confirm="archive"
        />
      </template>
    </template>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </UiPage>
</template>
