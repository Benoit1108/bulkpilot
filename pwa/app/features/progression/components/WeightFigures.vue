<script setup lang="ts">
import { formatFullDay } from '~/services/calendar/days'
import { formatKg, formatSignedKg, formatTrend } from '../format'
import type { WeightSummary } from '../progression'

/** Where the weight stands (spec §51): a day's weight moves, the averages and the trend are what to read. */
const props = defineProps<{ summary: WeightSummary }>()

const figures = computed(() => {
  const { latest, first, average7Kg, average30Kg, sinceStartKg } = props.summary
  return [
    { label: 'Dernière pesée', value: latest ? `${formatKg(latest.weightKg)} · ${formatFullDay(latest.day)}` : '—' },
    { label: 'Moyenne 7 jours', value: average7Kg === null ? 'aucune pesée ces 7 derniers jours' : formatKg(average7Kg) },
    { label: 'Moyenne 30 jours', value: average30Kg === null ? 'aucune pesée ces 30 derniers jours' : formatKg(average30Kg) },
    { label: 'Tendance sur 4 semaines', value: formatTrend(props.summary) },
    { label: 'Depuis le début', value: sinceStartKg === null || !first ? '—' : `${formatSignedKg(sinceStartKg)} (depuis le ${formatFullDay(first.day)})` },
  ]
})
</script>

<template>
  <dl class="m-0 grid gap-3 sm:grid-cols-2">
    <div
      v-for="figure in figures"
      :key="figure.label"
      class="flex flex-col"
    >
      <dt class="text-sm text-muted">
        {{ figure.label }}
      </dt>
      <dd class="m-0 font-semibold tabular-nums">
        {{ figure.value }}
      </dd>
    </div>
  </dl>
</template>
