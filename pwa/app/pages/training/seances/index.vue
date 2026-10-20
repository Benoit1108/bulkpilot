<script setup lang="ts">
import { History } from 'lucide-vue-next'
import { HISTORY_PAGE, TRAINING_KEYS } from '~/features/training/cacheKeys'
import { formatClock, workoutDuration } from '~/features/training/workout'

definePageMeta({ title: 'Historique' })

const api = useApi()
// Years of training: read a page at a time, the next one from the start of the last session shown.
const { firstPage, items: workouts, status, error, refresh, hasMore, loading, notice, list, keep, loadMore } = usePagedList(
  TRAINING_KEYS.history,
  cursor => api.get('/api/workouts', { query: { limit: HISTORY_PAGE, ...(cursor.before && { before: cursor.before }) } }),
  { size: HISTORY_PAGE, noun: ['séance'], cursor: last => ({ before: last.startedAt }) },
)
const dateFormat = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const items = computed(() => workouts.value.map(workout => ({
  id: workout.id,
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
  <UiPage>
    <ShellBackLink
      to="/training"
      label="Training"
    />
    <UiLoadError
      v-if="error && !firstPage"
      @retry="refresh()"
    />
    <template v-else-if="items.length">
      <UiAlert
        v-if="loading.message.value"
        tone="error"
      >
        {{ loading.message.value }}
      </UiAlert>
      <div
        ref="list"
        tabindex="-1"
      >
        <UiNavList
          label="Séances passées, la plus récente d’abord"
          :items="items"
          :keep="keep"
        />
      </div>
      <UiButton
        v-if="hasMore"
        variant="secondary"
        class="self-start"
        :loading="loading.pending.value"
        @click="loadMore"
      >
        Voir plus
      </UiButton>
    </template>
    <UiCard v-else-if="firstPage">
      <UiEmptyState
        :icon="History"
        title="Aucune séance terminée"
        description="Les séances apparaissent ici une fois terminées, avec leurs séries et leurs records."
      />
    </UiCard>
    <p
      v-else-if="status === 'pending'"
      class="text-muted"
      role="status"
    >
      Chargement…
    </p>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </UiPage>
</template>
