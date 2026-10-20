<script setup lang="ts">
import { LIST_ROW } from '~/components/ui/classes'
import { ListOrdered } from 'lucide-vue-next'
import type { Exercise } from '~/features/training/training'
import { formatTarget } from '~/features/training/training'
import { workingSetsOf, type WorkoutExercise, type WorkoutSet } from '~/features/training/workout'
import ExercisePicker from './ExercisePicker.vue'

/**
 * Moving through the workout: previous and next exercise, the list of all of them (with how far
 * each one went) to jump anywhere, and an exercise added on the spot.
 */
const props = defineProps<{ exercises: readonly WorkoutExercise[], sets: readonly WorkoutSet[], current: number }>()
const emit = defineEmits<{ go: [index: number], add: [exercise: Exercise] }>()

const listing = ref(false)
const adding = ref(false)
const isFirst = computed(() => props.current === 0)
const isLast = computed(() => props.current >= props.exercises.length - 1)

function jump(index: number): void {
  listing.value = false
  emit('go', index)
}
function add(chosen: Exercise): void {
  adding.value = false
  emit('add', chosen)
}
</script>

<template>
  <nav
    class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] gap-2"
    aria-label="Exercices de la séance"
  >
    <UiButton
      variant="secondary"
      :disabled="isFirst"
      @click="emit('go', current - 1)"
    >
      Précédent
    </UiButton>
    <UiButton
      variant="secondary"
      aria-label="Tous les exercices"
      :disabled="!exercises.length"
      @click="listing = true"
    >
      <ListOrdered
        :size="18"
        aria-hidden="true"
      />
    </UiButton>
    <UiButton
      variant="secondary"
      :disabled="isLast"
      @click="emit('go', current + 1)"
    >
      Suivant
    </UiButton>
  </nav>
  <UiButton
    variant="ghost"
    @click="adding = true"
  >
    Ajouter un exercice
  </UiButton>

  <UiDrawer
    v-model:open="listing"
    title="Exercices de la séance"
  >
    <ol
      class="flex flex-col"
      role="list"
    >
      <li
        v-for="planned in exercises"
        :key="planned.index"
      >
        <button
          type="button"
          class="group"
          :class="LIST_ROW"
          :aria-current="planned.index === current ? 'step' : undefined"
          @click="jump(planned.index)"
        >
          <span class="font-semibold group-aria-[current=step]:text-accent-ink">{{ planned.name }}</span>
          <span class="text-sm text-muted">{{ workingSetsOf(sets, planned.index).length }}/{{ planned.sets }} séries · {{ formatTarget(planned.repMin, planned.repMax, planned.measurement) }}</span>
        </button>
      </li>
    </ol>
  </UiDrawer>

  <UiDrawer
    v-model:open="adding"
    title="Ajouter un exercice"
  >
    <ExercisePicker
      v-if="adding"
      :create="false"
      @pick="add"
    />
  </UiDrawer>
</template>
