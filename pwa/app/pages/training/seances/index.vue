<script setup lang="ts">
import { History } from 'lucide-vue-next'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { formatClock, workoutDuration } from '~/features/training/workout'

definePageMeta({ title: 'Historique' })

const api = useApi()
const { data: workouts, error, refresh } = useAsyncData(TRAINING_KEYS.history, () => api.get('/api/workouts', { query: { limit: 50 } }))
const dateFormat = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const items = computed(() => (workouts.value ?? []).map(workout => ({
  to: `/training/seances/${workout.id}`,
  title: `${workout.dayName ?? 'Séance libre'} · ${dateFormat.format(new Date(workout.startedAt))}`,
  summary: [
    workout.finishedAt ? formatClock(workoutDuration(workout) ?? 0) : null,
    `${workout.exercises} exercice${workout.exercises > 1 ? 's' : ''}`,
    `${workout.workingSets} série${workout.workingSets > 1 ? 's' : ''}`,
    workout.volumeKg ? `${Math.round(workout.volumeKg).toLocaleString('fr-FR')}\u00A0kg soulevés` : null,
  ].filter(Boolean).join(' · '),
})))
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/training"
      label="Training"
    />
    <UiLoadError
      v-if="error && !workouts"
      @retry="refresh()"
    />
    <UiNavList
      v-else-if="items.length"
      label="Séances passées"
      :items="items"
    />
    <UiCard v-else-if="workouts">
      <UiEmptyState
        :icon="History"
        title="Aucune séance terminée"
        description="Les séances apparaissent ici une fois terminées, avec leurs séries et leurs records."
      />
    </UiCard>
  </div>
</template>
