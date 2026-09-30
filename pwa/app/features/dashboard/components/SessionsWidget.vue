<script setup lang="ts">
import { formatNumber } from '~/services/text/numbers'
import { plural } from '~/services/text/plural'
import { viewLong, viewPeriod, type DashboardView } from '../dashboard'
import { useTrainingAnalytics } from '../data'
import DashboardWidget from './DashboardWidget.vue'

/** Over a week or a month, the sessions done (spec §13), counted by the server. */
const props = defineProps<{ view: DashboardView, today: string }>()

const { data: analytics, status, error, refresh } = useTrainingAnalytics(() => viewPeriod(props.view), () => props.today)
</script>

<template>
  <DashboardWidget
    title="Entraînement"
    to="/training"
    :loading="status === 'pending' && !analytics"
    :failed="Boolean(error) && !analytics"
    @retry="refresh()"
  >
    <p v-if="analytics">
      <span class="text-2xl font-bold tabular-nums">{{ analytics.totals.sessions }}</span>
      {{ plural(analytics.totals.sessions, 'séance') }} {{ viewLong(view) }}
      <span
        v-if="analytics.sessionsPerWeek !== null"
        class="block text-sm text-muted"
      >{{ formatNumber(analytics.sessionsPerWeek) }} par semaine en moyenne</span>
    </p>
  </DashboardWidget>
</template>
