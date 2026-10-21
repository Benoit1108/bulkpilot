<script setup lang="ts">
import { formatKg, formatSignedKg } from '~/features/progression/format'
import { formatDayMonth } from '~/services/calendar/days'
import { viewLong, viewPeriod, type DashboardView } from '../dashboard'
import { useWeightSummary } from '../data'

/**
 * The current weight and how it moved (spec §13: « poids actuel, variation hebdomadaire »), as a
 * tile: the figure, the line of the 7-day mean, the change; the trend itself is on Progression.
 */
const props = defineProps<{ view: DashboardView, today: string }>()

// The day view shows the week's change (spec §13 « variation hebdomadaire »).
const { data: summary, status, error, refresh } = useWeightSummary(() => viewPeriod(props.view), () => props.today)
/** Over the days shown, as the server has it. */
const during = computed(() => (props.view === 'day' ? 'sur 7 jours' : viewLong(props.view)))
/** The 7-day mean of each weigh-in: the line of the tile, without a single day's noise. */
const trend = computed(() => (summary.value?.points ?? []).map(point => point.average7Kg))
</script>

<template>
  <UiTile
    title="Poids"
    to="/progression"
    :loading="status === 'pending' && !summary"
    :failed="Boolean(error) && !summary"
    @retry="refresh()"
  >
    <template v-if="summary?.latest">
      <p class="text-xs text-muted">
        <span class="block text-2xl font-bold text-ink tabular-nums">{{ formatKg(summary.latest.weightKg) }}</span> {{ formatDayMonth(summary.latest.day, today) }}
      </p>
      <UiSparkline :values="trend" />
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
  </UiTile>
</template>
