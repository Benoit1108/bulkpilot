<script setup lang="ts">
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
const gone = computed(() => error.value !== null && error.value !== undefined && asApiError(error.value).kind === 'not_found')
const current = computed(() => program.value !== null && program.value !== undefined && program.value.version === program.value.currentVersion)
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
  <div class="subpage">
    <ShellBackLink
      to="/training/programmes"
      label="Mes programmes"
    />
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
      <UiCard :title="program.name">
        <p class="meta">
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
          class="back-current"
        >
          Revenir à la version actuelle
        </NuxtLink>
        <ProgramSummary :days="program.days" />
        <div
          v-if="current"
          class="form__actions actions"
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
        v-if="program.versions.length > 1"
        title="Versions"
      >
        <p class="meta">
          Chaque modification des jours crée une version&nbsp;; tes séances passées restent liées à la leur.
        </p>
        <ul class="versions">
          <li
            v-for="past in program.versions"
            :key="past.number"
          >
            <NuxtLink
              :to="past.number === program.currentVersion ? `/training/programmes/${program.id}` : { query: { version: past.number } }"
              :aria-current="past.number === program.version ? 'page' : undefined"
            >
              Version {{ past.number }}<template v-if="past.number === program.currentVersion">
                (actuelle)
              </template>
            </NuxtLink>
            <span class="versions__date">{{ dateFormat.format(new Date(past.createdAt)) }}</span>
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
  </div>
</template>

<style scoped>
.meta {
  margin-bottom: var(--space-3);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.actions {
  margin-top: var(--space-4);
}

.back-current {
  display: inline-flex;
  align-items: center;
  min-height: var(--tap-target);
  margin-bottom: var(--space-2);
  color: var(--color-accent-text);
  font-weight: 600;
}

.versions {
  display: flex;
  flex-direction: column;
}

.versions li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  min-height: var(--tap-target);
  border-top: 1px solid var(--color-border);
}

.versions a {
  color: var(--color-accent-text);
  font-weight: 600;
}

.versions a[aria-current='page'] {
  color: inherit;
}

.versions__date {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
