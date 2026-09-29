<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next'
import ExerciseForm from '~/features/training/components/ExerciseForm.vue'
import { TRAINING_KEYS, isExerciseKey } from '~/features/training/cacheKeys'
import { EQUIPMENT, EXERCISE_TYPES, MEASUREMENTS, MUSCLES, formatRest, formatTarget, labelOf, type Exercise } from '~/features/training/training'
import { formatRecord } from '~/features/training/workout'
import { asApiError } from '~/services/api/client'

definePageMeta({ title: 'Exercice' })

const route = useRoute()
const id = computed(() => String(route.params.id))
const api = useApi()
const key = computed(() => TRAINING_KEYS.exercise(id.value))
const { data: exercise, error, refresh } = useAsyncData(key, () => api.get('/api/exercises/{id}', { path: { id: id.value } }))
// Records and sessions: secondary, loaded apart (and never blocking the page).
const { data: progress } = useAsyncData(() => `${key.value}-progress`, () => api.get('/api/exercises/{id}/progress', { path: { id: id.value } }))
const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' })
type Session = NonNullable<typeof progress.value>['sessions'][number]
function summary(session: Session): string {
  return [
    session.topLoadKg ? `${session.topLoadKg.toLocaleString('fr-FR')}\u00A0kg max` : null,
    session.bestReps ? `${session.bestReps}\u00A0reps` : null,
    session.bestSeconds ? `${session.bestSeconds}\u00A0s` : null,
    `${session.workingSets}\u00A0séries`,
  ].filter(Boolean).join(' · ')
}
const recent = computed(() => [...(progress.value?.sessions ?? [])].reverse().slice(0, 5))
const gone = computed(() => error.value !== null && error.value !== undefined && asApiError(error.value).kind === 'not_found')
const notice = ref<string | null>(null)
const removal = useSubmission()
const noting = useSubmission()
const note = ref('')
// Filled from the exercise once, and again only for another one: saving the exercise keeps a cue being typed.
watch(() => exercise.value?.id, () => (note.value = exercise.value?.note ?? ''), { immediate: true })

const facts = computed(() => {
  const value = exercise.value
  if (!value) return []
  return [
    { label: 'Muscle principal', value: labelOf(MUSCLES, value.primaryMuscle) },
    { label: 'Muscles secondaires', value: value.secondaryMuscles.map(muscle => labelOf(MUSCLES, muscle)).join(', ') || 'aucun' },
    { label: 'Type', value: labelOf(EXERCISE_TYPES, value.type) },
    { label: 'Équipement', value: labelOf(EQUIPMENT, value.equipment) },
    { label: 'Se note en', value: labelOf(MEASUREMENTS, value.measurement) },
    { label: 'Habituellement', value: [formatTarget(value.repMin, value.repMax, value.measurement), value.targetRir === null ? null : `RIR\u00A0${value.targetRir}`, `repos ${formatRest(value.restSeconds)}`, value.tempo ? `tempo ${value.tempo}` : null].filter(Boolean).join(' · ') },
  ]
})

function changed(value: Exercise): void {
  exercise.value = value
  // Every other view showing it is stale; this page already shows the answer.
  clearNuxtData(cached => isExerciseKey(cached) && cached !== key.value)
}

function onSaved(value: Exercise): void {
  changed(value)
  notice.value = 'Exercice enregistré.'
}

async function saveNote(): Promise<void> {
  await noting.run(async () => {
    changed(await api.put('/api/exercises/{id}/note', { text: note.value.trim() || null }, { path: { id: id.value } }))
    notice.value = note.value.trim() ? 'Consigne enregistrée.' : 'Consigne retirée.'
  })
}

async function destroy(): Promise<void> {
  if (await removal.run(() => api.delete('/api/exercises/{id}', { path: { id: id.value } }))) {
    clearNuxtData(isExerciseKey)
    await navigateTo('/training/exercices')
  }
}
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/training/exercices"
      label="Exercices"
    />
    <UiAlert
      v-if="gone"
      tone="error"
    >
      Cet exercice n’existe plus.
    </UiAlert>
    <UiLoadError
      v-else-if="error && !exercise"
      @retry="refresh()"
    />
    <template v-else-if="exercise">
      <UiCard :title="exercise.name">
        <dl class="facts">
          <div
            v-for="fact in facts"
            :key="fact.label"
          >
            <dt>{{ fact.label }}</dt>
            <dd>{{ fact.value }}</dd>
          </div>
        </dl>
        <p
          v-if="exercise.description"
          class="description"
        >
          {{ exercise.description }}
        </p>
        <a
          v-if="exercise.mediaUrl"
          :href="exercise.mediaUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="media"
        >
          Voir le mouvement
          <ExternalLink
            :size="16"
            aria-hidden="true"
          />
          <span class="visually-hidden">(nouvel onglet)</span>
        </a>
        <p
          v-if="exercise.source === 'library'"
          class="source"
        >
          Exercice de la bibliothèque, commun à tous.
        </p>
      </UiCard>

      <UiCard
        v-if="progress && !progress.records.length"
        title="Mes records"
      >
        <p class="records__date">
          Pas encore de record&nbsp;: ils apparaîtront après ta première séance avec cet exercice.
        </p>
      </UiCard>
      <UiCard
        v-else-if="progress"
        title="Mes records"
      >
        <ul class="records">
          <li
            v-for="record in progress.records"
            :key="record.kind"
          >
            {{ formatRecord(record, exercise.measurement) }}
            <span class="records__date">{{ dateFormat.format(new Date(record.date)) }}</span>
          </li>
        </ul>
        <h3 class="records__title">
          Dernières séances
        </h3>
        <ul class="records">
          <li
            v-for="session in recent"
            :key="session.workoutId"
          >
            <NuxtLink :to="`/training/seances/${session.workoutId}`">
              {{ dateFormat.format(new Date(session.date)) }}
            </NuxtLink>
            <span class="records__date">{{ summary(session) }}</span>
          </li>
        </ul>
      </UiCard>

      <UiCard title="Ma consigne">
        <form
          class="form"
          novalidate
          @submit.prevent="saveNote"
        >
          <UiAlert
            v-if="noting.message.value"
            tone="error"
          >
            {{ noting.message.value }}
          </UiAlert>
          <UiTextarea
            v-model="note"
            label="Consigne"
            :maxlength="500"
            :rows="2"
            hint="Affichée à chaque fois que tu fais l’exercice, par exemple «&nbsp;amplitude complète&nbsp;»."
            :error="noting.fields.value.text"
          />
          <UiButton
            type="submit"
            variant="secondary"
            :loading="noting.pending.value"
          >
            Enregistrer la consigne
          </UiButton>
        </form>
      </UiCard>

      <template v-if="exercise.editable">
        <UiCard title="Modifier">
          <ExerciseForm
            :key="exercise.id"
            :exercise="exercise"
            @saved="onSaved"
          />
        </UiCard>
        <UiAlert
          v-if="removal.message.value"
          tone="error"
        >
          {{ removal.message.value }}
        </UiAlert>
        <UiConfirmButton
          label="Supprimer l’exercice"
          :loading="removal.pending.value"
          @confirm="destroy"
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
.facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: var(--space-3);
}

.facts dt {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.facts dd {
  font-weight: 600;
}

.description {
  margin-top: var(--space-3);
  white-space: pre-line;
}

.records {
  display: flex;
  flex-direction: column;
}

.records li {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: var(--space-1) var(--space-3);
  padding: var(--space-2) 0;
  border-top: 1px solid var(--color-border);
}

.records a {
  color: var(--color-accent-text);
  font-weight: 600;
}

.records__title {
  margin-top: var(--space-3);
  font-weight: 700;
}

.records__date {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.media {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  min-height: var(--tap-target);
  margin-top: var(--space-2);
  color: var(--color-accent-text);
  font-weight: 600;
}

.source {
  margin-top: var(--space-2);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
