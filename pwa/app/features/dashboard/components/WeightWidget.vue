<script setup lang="ts">
import { formatKg, formatSignedKg, formatTrend } from '~/features/progression/format'
import { formatDayMonth } from '~/services/calendar/days'
import { viewLong, viewPeriod, type DashboardView } from '../dashboard'
import { useWeightSummary } from '../data'
import DashboardWidget from './DashboardWidget.vue'

/** The current weight and how it moves (spec §13: « poids actuel, variation hebdomadaire »). */
const props = defineProps<{ view: DashboardView, today: string }>()

// The day view shows the week's change (spec §13 « variation hebdomadaire »).
const { data: summary, status, error, refresh } = useWeightSummary(() => viewPeriod(props.view), () => props.today)
/** Over the days shown, as the server has it. */
const during = computed(() => (props.view === 'day' ? 'sur 7 jours' : viewLong(props.view)))
</script>

<template>
  <DashboardWidget
    title="Poids"
    to="/progression"
    :loading="status === 'pending' && !summary"
    :failed="Boolean(error) && !summary"
    @retry="refresh()"
  >
    <template v-if="summary?.latest">
      <p>
        <span class="text-2xl font-bold tabular-nums">{{ formatKg(summary.latest.weightKg) }}</span>
        <span class="text-sm text-muted"> · {{ formatDayMonth(summary.latest.day, today) }}</span>
      </p>
      <p class="text-sm">
        Tendance&nbsp;: {{ formatTrend(summary) }}
      </p>
      <p
        v-if="summary.periodChangeKg !== null"
        class="text-sm"
      >
        {{ formatSignedKg(summary.periodChangeKg) }} {{ during }}
      </p>
    </template>
    <div
      v-else
      class="flex flex-col gap-2"
    >
      <p class="text-sm text-muted">
        Aucune pesée pour l’instant&nbsp;: la première lance le suivi et la tendance.
      </p>
      <UiLinkButton
        :to="{ path: '/progression', query: { ajouter: 'pesee' } }"
        variant="secondary"
      >
        Ajouter une pesée
      </UiLinkButton>
    </div>
  </DashboardWidget>
</template>
