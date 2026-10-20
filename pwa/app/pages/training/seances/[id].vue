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
usePageHeading(() => (workout.value ? workout.value.programDay?.dayName ?? 'Séance libre' : null), { named: true })
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
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/training/seances"
      label="Historique"
    />
    <UiAlert
      v-if="missing && !workout"
      tone="info"
    >
      Séance terminée&nbsp;! Elle est gardée sur cet appareil et sera enregistrée dès le retour du réseau.
    </UiAlert>
    <UiLoadError
      v-else-if="error && !workout"
      @retry="refresh()"
    />
    <template v-else-if="workout">
      <UiCard>
        <p class="text-sm text-muted">
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
          <p class="font-bold">
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
        <p class="text-sm text-muted">
          Prévu&nbsp;: {{ prescription(exercise, { rest: false }) }}
        </p>
        <ol
          v-if="setsOf(exercise.index).length"
          class="mt-2 flex flex-col gap-1 tabular-nums"
          role="list"
        >
          <li
            v-for="(set, n) in setsOf(exercise.index)"
            :key="set.id"
          >
            <span class="inline-block w-6 text-sm text-muted"><span class="sr-only">Série </span>{{ n + 1 }}</span>
            <span class="inline-block min-w-28 text-sm text-muted">{{ labelOf(SET_KINDS, set.kind) }}</span>
            {{ formatSet(set, exercise.measurement) }}
          </li>
        </ol>
        <p
          v-else
          class="text-sm text-muted"
        >
          Pas fait.
        </p>
      </UiCard>
    </template>
  </div>
</template>
