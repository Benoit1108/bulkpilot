<script setup lang="ts">
import type { DashboardView } from '../dashboard'
import WeeklyReportCard from '~/features/coach/components/WeeklyReportCard.vue'
import { useCoachOfDay } from '~/features/coach/coachOfDay'

/** The last weekly report (spec §59), drawn by the Coach once a week is over. */
// Every widget is given the view; this one is the same whatever it is.
const props = defineProps<{ today: string, view?: DashboardView }>()

const { coach, status, error, refresh } = useCoachOfDay(() => props.today)
</script>

<template>
  <WeeklyReportCard
    v-if="coach?.report"
    :report="coach.report"
    :today="today"
    link-to-all
  />
  <UiTile
    v-else
    title="Rapport de la semaine"
    to="/coach/rapports"
    :loading="status === 'pending' && !coach"
    :failed="Boolean(error) && !coach"
    @retry="refresh()"
  >
    <p class="text-sm text-muted">
      Ton premier rapport arrivera après ta première semaine complète.
    </p>
  </UiTile>
</template>
