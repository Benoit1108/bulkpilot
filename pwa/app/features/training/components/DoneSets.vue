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
    class="done"
    aria-label="Séries faites"
  >
    <li
      v-for="(set, i) in sets"
      :key="set.id"
      class="done__set"
      :class="{ 'done__set--editing': set.id === editing }"
    >
      <span>
        <span class="done__kind">{{ i + 1 }}. {{ labelOf(SET_KINDS, set.kind) }}</span>
        {{ formatSet(set, exercise.measurement) }}
      </span>
      <span class="done__actions">
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

<style scoped>
.done {
  display: flex;
  flex-direction: column;
}

.done__set {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-1) var(--space-2);
  border-top: 1px solid var(--color-border);
  font-variant-numeric: tabular-nums;
}

.done__set--editing {
  background: var(--color-surface-raised);
  box-shadow: inset 3px 0 0 var(--color-accent);
}

.done__kind {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.done__actions {
  display: flex;
  gap: var(--space-2);
}
</style>
