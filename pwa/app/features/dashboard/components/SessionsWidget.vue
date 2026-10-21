<script setup lang="ts">
import { endedWeeks } from '~/features/progression/format'
import { weekStart } from '~/services/calendar/days'
import { formatNumber } from '~/services/text/numbers'
import { plural } from '~/services/text/plural'
import { viewLong, viewPeriod, type DashboardView } from '../dashboard'
import { useTrainingAnalytics } from '../data'

/** Over a week or a month, the sessions done (spec §13), counted by the server. */
const props = defineProps<{ view: DashboardView, today: string }>()

const { data: analytics, status, error, refresh } = useTrainingAnalytics(() => viewPeriod(props.view), () => props.today)
</script>

<template>
  <UiTile
    title="Entraînement"
    to="/training"
    :loading="status === 'pending' && !analytics"
    :failed="Boolean(error) && !analytics"
    @retry="refresh()"
  >
    <template v-if="analytics">
      <p class="text-sm">
        <span class="block text-2xl font-bold tabular-nums">{{ analytics.totals.sessions }}</span> {{ plural(analytics.totals.sessions, 'séance') }} {{ viewLong(view) }}
      </p>
      <UiMiniBars :values="endedWeeks(analytics.weeks, weekStart(today)).map(week => week.sessions)" />
      <p
        v-if="analytics.sessionsPerWeek !== null"
        class="text-xs text-muted"
      >
        {{ formatNumber(analytics.sessionsPerWeek) }} par semaine en moyenne
      </p>
    </template>
  </UiTile>
</template>
