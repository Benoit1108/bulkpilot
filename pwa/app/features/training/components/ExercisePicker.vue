<script setup lang="ts">
import { LIST_ROW, TEXT_LINK } from '~/components/ui/classes'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { EQUIPMENT, MUSCLES, labelOf, type Exercise, type Muscle } from '~/features/training/training'
import { matches } from '~/services/text/search'

/**
 * Finds an exercise to add: the whole list (library and own ones) is small, so it is filtered on
 * the device as the user types, by name and muscle.
 */
const props = withDefaults(defineProps<{
  /** Offer to create a missing exercise (it leaves the page: not from a workout). */
  create?: boolean
}>(), { create: true })
const emit = defineEmits<{ pick: [exercise: Exercise] }>()

const api = useApi()
const { data: exercises, error, refresh } = useAsyncData(TRAINING_KEYS.exercises, () => api.get('/api/exercises'))
const query = ref('')
const muscle = ref<Muscle | null>(null)

const found = computed(() => (exercises.value ?? []).filter(exercise =>
  matches(exercise.name, query.value)
  && (muscle.value === null || exercise.primaryMuscle === muscle.value || exercise.secondaryMuscles.includes(muscle.value)),
))
/** Long lists stay quick to render; typing narrows them. */
const SHOWN = 40
const shown = computed(() => found.value.slice(0, SHOWN))
/** The count is announced once typing pauses, not on every key. */
const count = ref<number | null>(null)
let counting: ReturnType<typeof setTimeout> | undefined
watch([() => found.value.length, () => exercises.value !== undefined && exercises.value !== null], ([length, ready]) => {
  clearTimeout(counting)
  if (ready) counting = setTimeout(() => (count.value = length), count.value === null ? 0 : 500)
}, { immediate: true })
onBeforeUnmount(() => clearTimeout(counting))

function detail(exercise: Exercise): string {
  return [labelOf(MUSCLES, exercise.primaryMuscle), labelOf(EQUIPMENT, exercise.equipment), exercise.source === 'custom' ? 'perso' : null].filter(Boolean).join(' · ')
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <UiInput
      v-model="query"
      label="Rechercher un exercice"
      autocomplete="off"
      hint="Par exemple «&nbsp;développé incliné&nbsp;» ou «&nbsp;curl&nbsp;»."
      autofocus
    />
    <UiSelect
      v-model="muscle"
      label="Muscle"
      placeholder="Tous les muscles"
      :options="MUSCLES"
    />
    <UiLoadError
      v-if="error && !exercises"
      @retry="refresh()"
    />
    <p
      v-else
      class="text-sm text-muted"
      role="status"
    >
      <template v-if="count !== null">
        {{ count }}&nbsp;exercice{{ count > 1 ? 's' : '' }}<template v-if="count > SHOWN">
          , affine la recherche pour voir les autres
        </template>
      </template>
    </p>
    <ul
      v-if="shown.length"
      class="flex flex-col"
      aria-label="Exercices"
    >
      <li
        v-for="exercise in shown"
        :key="exercise.id"
      >
        <button
          type="button"
          :class="LIST_ROW"
          @click="emit('pick', exercise)"
        >
          <span class="font-semibold">{{ exercise.name }}</span>
          <span class="text-sm text-muted">{{ detail(exercise) }}</span>
        </button>
      </li>
    </ul>
    <NuxtLink
      v-if="props.create"
      to="/training/exercices/nouveau"
      :class="TEXT_LINK"
    >
      Exercice introuvable&nbsp;? Créer un exercice
    </NuxtLink>
  </div>
</template>
