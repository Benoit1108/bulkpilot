<script setup lang="ts">
import type { WorkoutExercise } from '~/features/training/workout'

/**
 * Finishing is the user's call (spec §46): with sets left, carrying on is the obvious choice and
 * finishing anyway the deliberate one.
 */
defineProps<{ left: WorkoutExercise[] }>()
const emit = defineEmits<{ finish: [], resume: [], discard: [] }>()
</script>

<template>
  <div class="finish">
    <template v-if="left.length">
      <p>
        Il reste des séries sur {{ left.length }}&nbsp;exercice{{ left.length > 1 ? 's' : '' }}&nbsp;: {{ left.map(exercise => exercise.name).join(', ') }}.
      </p>
      <UiButton @click="emit('resume')">
        Continuer la séance
      </UiButton>
      <UiButton
        variant="secondary"
        @click="emit('finish')"
      >
        Terminer quand même
      </UiButton>
    </template>
    <template v-else>
      <p>Toutes les séries prévues sont faites.</p>
      <UiButton @click="emit('finish')">
        Terminer la séance
      </UiButton>
      <UiButton
        variant="secondary"
        @click="emit('resume')"
      >
        Continuer la séance
      </UiButton>
    </template>
    <UiConfirmButton
      label="Abandonner la séance"
      confirm-label="Confirmer l’abandon (rien n’est gardé)"
      variant="ghost"
      @confirm="emit('discard')"
    />
  </div>
</template>

<style scoped>
.finish {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
</style>
