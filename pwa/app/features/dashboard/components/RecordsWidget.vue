<script setup lang="ts">
import { plural } from '~/services/text/plural'
import { viewLong, viewPeriod, type DashboardView } from '../dashboard'
import { useTrainingAnalytics } from '../data'
import DashboardWidget from './DashboardWidget.vue'

/** The records beaten lately (spec §13 « PR éventuels »), counted by the server. */
const props = defineProps<{ view: DashboardView, today: string }>()

// The day view looks at the last 7 days: a record is rare enough for one day to be empty most of the time.
const shownView = computed(() => (props.view === 'month' ? 'month' : 'week'))
const { data: analytics, status, error, refresh } = useTrainingAnalytics(() => viewPeriod(props.view), () => props.today)
const records = computed(() => analytics.value?.totals.records ?? 0)
</script>

<template>
  <DashboardWidget
    title="Records"
    to="/training/seances"
    link-label="Séances"
    :loading="status === 'pending' && !analytics"
    :failed="Boolean(error) && !analytics"
    @retry="refresh()"
  >
    <p v-if="analytics && records">
      <span class="text-2xl font-bold tabular-nums">{{ records }}</span>
      {{ plural(records, 'record') }} {{ plural(records, 'battu') }} {{ viewLong(shownView) }}
      <span class="block text-sm text-muted">en {{ analytics.totals.sessions }} {{ plural(analytics.totals.sessions, 'séance') }}</span>
    </p>
    <p
      v-else
      class="text-sm text-muted"
    >
      Aucun record battu {{ viewLong(shownView) }}.
    </p>
  </DashboardWidget>
</template>
