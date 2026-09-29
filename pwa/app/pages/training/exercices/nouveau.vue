<script setup lang="ts">
import ExerciseForm from '~/features/training/components/ExerciseForm.vue'
import { isExerciseKey } from '~/features/training/cacheKeys'
import type { Exercise } from '~/features/training/training'

definePageMeta({ title: 'Nouvel exercice' })

async function onSaved(exercise: Exercise): Promise<void> {
  clearNuxtData(isExerciseKey)
  await navigateTo(`/training/exercices/${exercise.id}`)
}
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/training/exercices"
      label="Exercices"
    />
    <UiCard>
      <ExerciseForm @saved="onSaved" />
    </UiCard>
  </div>
</template>
