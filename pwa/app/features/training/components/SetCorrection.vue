<script setup lang="ts">
import type { SetValues } from '~/features/training/session'
import { labelOf } from '~/features/training/training'
import { SET_KINDS, type WorkoutExercise, type WorkoutSet } from '~/features/training/workout'
import SetForm from './SetForm.vue'

/** A set done earlier, corrected in its own form (on its own exercise, whatever is on screen). */
defineProps<{ exercise: WorkoutExercise, set: WorkoutSet, number: number }>()
const emit = defineEmits<{ submit: [values: SetValues], cancel: [] }>()

const form = ref<{ focus: () => void } | null>(null)
defineExpose({ focus: () => form.value?.focus() })
</script>

<template>
  <div
    class="flex flex-col gap-3 rounded-md border-2 border-accent p-3"
    data-testid="correction"
  >
    <h3 class="font-bold">
      Correction de la série {{ number }} ({{ labelOf(SET_KINDS, set.kind) }})
    </h3>
    <SetForm
      ref="form"
      :key="`edit-${set.id}`"
      :exercise="exercise"
      :initial="set"
      submit-label="Enregistrer la correction"
      correcting
      @submit="emit('submit', $event)"
      @cancel="emit('cancel')"
    />
  </div>
</template>
