<script setup lang="ts">
import { labelOf } from '~/features/training/training'
import { formatSet, SET_KINDS, type WorkoutExercise, type WorkoutSet } from '~/features/training/workout'

/** The sets of the exercise done so far: each can be corrected, or removed in two taps. */
defineProps<{ exercise: WorkoutExercise, sets: WorkoutSet[], editing: string | null }>()
const emit = defineEmits<{ correct: [set: WorkoutSet], remove: [set: WorkoutSet] }>()

const buttons = new Map<string, { $el: HTMLElement }>()
function keep(id: string, button: unknown): void {
  if (button) buttons.set(id, button as { $el: HTMLElement })
  else buttons.delete(id)
}
/** Back to a set's correct button (after correcting it). */
defineExpose({ focus: (id: string) => buttons.get(id)?.$el.focus() })
</script>

<template>
  <ol
    class="flex flex-col"
    aria-label="Séries faites"
  >
    <li
      v-for="(set, i) in sets"
      :key="set.id"
      class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 border-t border-line tabular-nums"
      :class="{ 'bg-surface-raised shadow-mark': set.id === editing }"
    >
      <span>
        <span class="text-sm text-muted">{{ i + 1 }}. {{ labelOf(SET_KINDS, set.kind) }}</span>
        {{ formatSet(set, exercise.measurement) }}
      </span>
      <span class="flex gap-2">
        <UiButton
          :ref="button => keep(set.id, button)"
          variant="ghost"
          :aria-label="`Corriger la série ${i + 1}`"
          @click="emit('correct', set)"
        >
          Corriger
        </UiButton>
        <UiConfirmButton
          label="Supprimer"
          confirm-label="Confirmer"
          variant="ghost"
          @confirm="emit('remove', set)"
        />
      </span>
    </li>
  </ol>
</template>
