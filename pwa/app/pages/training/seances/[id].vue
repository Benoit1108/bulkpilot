<script setup lang="ts">
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { useWorkoutOutbox } from '~/features/training/outbox'
import { labelOf, prescription } from '~/features/training/training'
import { formatClock, formatRecord, formatSet, SET_KINDS, workoutDuration, type WorkoutSet } from '~/features/training/workout'

definePageMeta({ title: 'Séance' })

const route = useRoute()
const id = computed(() => String(route.params.id))
const api = useApi()
const outbox = useWorkoutOutbox()
const { data: workout, error, refresh } = useAsyncData(() => TRAINING_KEYS.workout(id.value), () => api.get('/api/workouts/{id}', { path: { id: id.value } }))
/** Finished on this device and not sent yet: the API does not know it. */
const sending = ref(false)
onMounted(async () => {
  sending.value = await outbox.isEnding(id.value)
})
const missing = computed(() => sending.value && error.value !== null && error.value !== undefined)
// Just finished offline: shown again once the device sent it.
const stop = outbox.onSent((write) => {
  if (write.workoutId !== id.value) return
  sending.value = false
  void refresh()
})
onBeforeUnmount(stop)

const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'full', timeStyle: 'short' })
const duration = computed(() => {
  const lasted = workout.value ? workoutDuration(workout.value) : null
  return lasted === null ? null : formatClock(lasted)
})
const records = computed(() => (workout.value?.exercises ?? []).flatMap(exercise => exercise.newRecords.map(record => `${exercise.name}\u00A0— ${formatRecord(record, exercise.measurement)}`)))
const setsOf = (index: number): WorkoutSet[] => (workout.value?.sets ?? []).filter(set => set.exerciseIndex === index)
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/training/seances"
      label="Historique"
    />
    <UiAlert
      v-if="missing && !workout"
      tone="info"
    >
      Séance terminée&nbsp;! Elle est gardée sur le téléphone et sera enregistrée dès le retour du réseau.
    </UiAlert>
    <UiLoadError
      v-else-if="error && !workout"
      @retry="refresh()"
    />
    <template v-else-if="workout">
      <UiCard :title="workout.programDay?.dayName ?? 'Séance libre'">
        <p class="meta">
          {{ dateFormat.format(new Date(workout.startedAt)) }}<template v-if="duration">
            · {{ duration }}
          </template><template v-if="workout.status === 'in_progress'">
            · en cours
          </template>
        </p>
        <UiAlert
          v-if="records.length"
          tone="success"
        >
          <p class="records__title">
            Records battus
          </p>
          <ul>
            <li
              v-for="record in records"
              :key="record"
            >
              {{ record }}
            </li>
          </ul>
        </UiAlert>
      </UiCard>
      <UiCard
        v-for="exercise in workout.exercises"
        :key="exercise.index"
        :title="exercise.name"
      >
        <p class="meta">
          Prévu&nbsp;: {{ prescription(exercise, { rest: false }) }}
        </p>
        <ol
          v-if="setsOf(exercise.index).length"
          class="sets"
        >
          <li
            v-for="set in setsOf(exercise.index)"
            :key="set.id"
          >
            <span class="sets__kind">{{ labelOf(SET_KINDS, set.kind) }}</span>
            {{ formatSet(set, exercise.measurement) }}
          </li>
        </ol>
        <p
          v-else
          class="meta"
        >
          Pas fait.
        </p>
      </UiCard>
    </template>
  </div>
</template>

<style scoped>
.meta {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.records__title {
  font-weight: 700;
}

.sets {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin-top: var(--space-2);
  font-variant-numeric: tabular-nums;
}

.sets__kind {
  display: inline-block;
  min-width: 7rem;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
