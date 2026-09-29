<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next'
import ExerciseForm from '~/features/training/components/ExerciseForm.vue'
import ExerciseRecords from '~/features/training/components/ExerciseRecords.vue'
import { TRAINING_KEYS, isExerciseKey } from '~/features/training/cacheKeys'
import { EQUIPMENT, EXERCISE_TYPES, MEASUREMENTS, MUSCLES, formatRest, formatTarget, labelOf, type Exercise } from '~/features/training/training'
import { asApiError } from '~/services/api/client'

definePageMeta({ title: 'Exercice' })

const route = useRoute()
const id = computed(() => String(route.params.id))
const api = useApi()
const key = computed(() => TRAINING_KEYS.exercise(id.value))
const { data: exercise, error, refresh } = useAsyncData(key, () => api.get('/api/exercises/{id}', { path: { id: id.value } }))
// Records and sessions: secondary, loaded apart (and never blocking the page).
const { data: progress } = useAsyncData(() => `${key.value}-progress`, () => api.get('/api/exercises/{id}/progress', { path: { id: id.value } }))
const gone = computed(() => error.value !== null && error.value !== undefined && asApiError(error.value).kind === 'not_found')
const notice = ref<string | null>(null)
const removal = useSubmission()
const noting = useSubmission()
const note = ref('')
const noteGuard = useUnsavedChanges(() => note.value.trim())
// Filled from the exercise once, and again only for another one: saving the exercise keeps a cue being typed.
watch(() => exercise.value?.id, () => {
  note.value = exercise.value?.note ?? ''
  noteGuard.markSaved(note.value.trim())
}, { immediate: true })

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
  const sent = note.value.trim()
  await noting.run(async () => {
    changed(await api.put('/api/exercises/{id}/note', { text: sent || null }, { path: { id: id.value } }))
    noteGuard.markSaved(sent)
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
  <div class="flex max-w-reading flex-col gap-4">
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
        <dl class="grid grid-cols-[repeat(auto-fit,minmax(12rem,1fr))] gap-3">
          <div
            v-for="fact in facts"
            :key="fact.label"
          >
            <dt class="text-sm text-muted">
              {{ fact.label }}
            </dt>
            <dd class="font-semibold">
              {{ fact.value }}
            </dd>
          </div>
        </dl>
        <p
          v-if="exercise.description"
          class="mt-3 whitespace-pre-line"
        >
          {{ exercise.description }}
        </p>
        <a
          v-if="exercise.mediaUrl"
          :href="exercise.mediaUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-2 inline-flex min-h-tap items-center gap-1 font-semibold text-accent-ink"
        >
          Voir le mouvement
          <ExternalLink
            :size="16"
            aria-hidden="true"
          />
          <span class="sr-only">(nouvel onglet)</span>
        </a>
        <p
          v-if="exercise.source === 'library'"
          class="mt-2 text-sm text-muted"
        >
          Exercice de la bibliothèque, commun à tous.
        </p>
      </UiCard>

      <ExerciseRecords
        v-if="progress"
        :progress="progress"
        :measurement="exercise.measurement"
      />

      <UiCard title="Ma consigne">
        <form
          class="flex flex-col gap-4"
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
