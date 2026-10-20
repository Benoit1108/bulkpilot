<script setup lang="ts">
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import ExerciseIllustration from '~/features/training/components/ExerciseIllustration.vue'
import { supersetLine } from '~/features/training/nextSet'
import { prescription } from '~/features/training/training'
import { formatRecord, formatSet, formatSuggestion, isWorking, type SetKind, type WorkoutExercise, type WorkoutSet } from '~/features/training/workout'

/**
 * The exercise on screen: its place in a superset, the plan and cues, which set comes next, and
 * what the API said about it (last time, suggestion, records beaten today).
 */
const props = defineProps<{
  exercise: WorkoutExercise
  exercises: readonly WorkoutExercise[]
  /** The sets already done on this exercise. */
  own: readonly WorkoutSet[]
  kind: SetKind
}>()

// The exercises' list (library keys, muscles), shared with the exercise pages and kept once read in
// this visit (not read again at every exercise); without signal and never read, no picture.
const api = useApi()
const { data: library } = useAsyncData(TRAINING_KEYS.exercises, () => api.get('/api/exercises'), {
  lazy: true,
  getCachedData: (key, nuxtApp) => nuxtApp.payload.data[key] ?? nuxtApp.static.data[key],
})
const known = computed(() => library.value?.find(entry => entry.id === props.exercise.exerciseId) ?? null)

const heading = ref<HTMLElement | null>(null)
defineExpose({ focus: () => heading.value?.focus() })

const superset = computed(() => supersetLine(props.exercise, props.exercises))
const cues = computed(() => [props.exercise.note, props.exercise.programNotes].filter((cue): cue is string => Boolean(cue)))
const working = computed(() => props.own.filter(isWorking).length)
const warmups = computed(() => props.own.filter(set => set.kind === 'warmup').length)
const previous = computed(() => props.exercise.previous?.sets.filter(isWorking) ?? [])
const hasFacts = computed(() => previous.value.length > 0 || Boolean(props.exercise.suggestion) || props.exercise.newRecords.length > 0)
const records = computed(() => props.exercise.newRecords.map(record => formatRecord(record, props.exercise.measurement)).join(' · '))
</script>

<template>
  <p
    v-if="superset"
    class="text-sm font-bold text-accent-ink"
  >
    {{ superset }}
  </p>
  <h2
    id="exercise-title"
    ref="heading"
    class="text-title font-extrabold focus:outline-none"
    tabindex="-1"
  >
    {{ exercise.name }}
  </h2>
  <p class="font-semibold text-muted">
    {{ prescription(exercise, { rest: false, tempo: true }) }}
  </p>
  <!-- Folded: the set form stays within the thumb's reach; one tap shows the movement. -->
  <details
    v-if="known"
    class="text-sm"
  >
    <summary class="min-h-tap cursor-pointer content-center font-semibold text-accent-ink">
      Voir le mouvement
    </summary>
    <ExerciseIllustration
      :library-key="known.libraryKey"
      :name="exercise.name"
      :primary="known.primaryMuscle"
      :secondary="known.secondaryMuscles"
      compact
    />
  </details>
  <p
    v-for="cue in cues"
    :key="cue"
    class="rounded-sm border-l-3 border-accent bg-surface-raised px-3 py-2"
  >
    {{ cue }}
  </p>
  <p class="text-lg font-extrabold">
    <template v-if="kind === 'warmup'">
      Échauffement {{ warmups + 1 }}/{{ exercise.warmupSets }}
    </template>
    <template v-else-if="kind === 'drop'">
      Drop set, sans repos
    </template>
    <template v-else-if="working < exercise.sets">
      Série {{ working + 1 }}/{{ exercise.sets }}
    </template>
    <template v-else>
      Séries prévues faites
    </template>
  </p>
  <div
    v-if="hasFacts"
    class="flex flex-col gap-2 tabular-nums"
  >
    <div v-if="previous.length">
      <p class="text-sm text-muted">
        Dernière fois
      </p>
      <ol
        class="m-0 flex list-none flex-wrap gap-x-3 gap-y-1 p-0 font-bold"
        data-testid="previous-sets"
      >
        <li
          v-for="set in previous"
          :key="set.id"
        >
          {{ formatSet(set, exercise.measurement) }}
        </li>
      </ol>
    </div>
    <p v-if="exercise.suggestion">
      <span class="text-sm text-muted">Suggestion&nbsp;:</span> {{ formatSuggestion(exercise.suggestion, exercise) }}
    </p>
    <p
      v-if="exercise.newRecords.length"
      class="font-bold"
    >
      <span class="text-sm text-muted">Records battus aujourd’hui&nbsp;:</span> {{ records }}
    </p>
  </div>
</template>
