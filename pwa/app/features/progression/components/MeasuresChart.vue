<script setup lang="ts">
import type { ChartSeries } from '~/components/ui/LineChart.vue'
import { formatFullDay, formatShortDay } from '~/services/calendar/days'
import { formatCm, formatSignedCm, periodLong } from '../format'
import { measureName, type MeasureSeries, type Period } from '../progression'
import PeriodPicker from './PeriodPicker.vue'

/**
 * Each measure's latest value and change over the period with its small line, and the chart of
 * the one picked (spec §55).
 */
const props = defineProps<{ series: MeasureSeries[] }>()
const period = defineModel<Period>('period', { required: true })

const measured = computed(() => props.series.filter(series => series.latest))
const picked = ref<string | null>(null)
const shown = computed(() => measured.value.find(series => series.key === picked.value) ?? measured.value[0] ?? null)
const options = computed(() => measured.value.map(series => ({ value: series.key, label: measureName(series) })))
const chart = computed<ChartSeries[]>(() => (shown.value ? [{ name: measureName(shown.value), variant: 'line', tone: 'accent', points: shown.value.points.map(point => ({ x: point.day, y: point.cm })) }] : []))

const during = computed(() => periodLong(period.value))
const summary = computed(() => {
  const series = shown.value
  if (!series || !series.points.length) return `Pas de mesure ${during.value}.`
  if (series.changeCm === null) return `Une mesure ${during.value}\u00A0: ${formatCm(series.points[0]!.cm)}.`
  return `${measureName(series)}\u00A0: ${formatSignedCm(series.changeCm)} ${during.value}, ${formatCm(series.latest!.cm)} à la dernière mesure, le ${formatFullDay(series.latest!.day)}.`
})
/** Each measure's line over the period, beside its change (the shape only, the figures are in the table). */
const lines = computed(() => Object.fromEntries(measured.value.map(series => [series.key, series.points.map(point => point.cm)])))
const change = (series: MeasureSeries) => (series.changeCm === null ? '—' : formatSignedCm(series.changeCm))
</script>

<template>
  <div class="flex flex-col gap-4">
    <PeriodPicker
      v-model="period"
      name="measurements-period"
    />
    <table class="w-full border-collapse tabular-nums [&_:is(td,th)]:border-b [&_:is(td,th)]:border-line [&_:is(td,th)]:py-2 [&_:is(td,th)]:text-right [&_:is(td,th):first-child]:text-left">
      <thead>
        <tr class="text-sm text-muted">
          <th scope="col">
            Mesure
          </th>
          <th scope="col">
            Dernière
          </th>
          <th scope="col">
            Sur la période
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="measure in measured"
          :key="measure.key"
        >
          <th
            scope="row"
            class="font-semibold"
          >
            {{ measureName(measure) }}
          </th>
          <td>{{ formatCm(measure.latest!.cm) }}</td>
          <td>
            <span class="inline-flex items-center justify-end gap-2">
              <span class="w-14 max-sm:hidden"><UiSparkline :values="lines[measure.key] ?? []" /></span>
              {{ change(measure) }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
    <UiSelect
      v-if="options.length > 1"
      :model-value="shown?.key ?? null"
      label="Courbe de"
      :options="options"
      :nullable="false"
      @update:model-value="picked = $event"
    />
    <UiLineChart
      v-if="shown && shown.points.length"
      :title="measureName(shown)"
      :summary="summary"
      :series="chart"
      :format-y="formatCm"
      :format-x="formatShortDay"
    >
      <template #table>
        <ul class="mt-2 flex list-none flex-col p-0 tabular-nums">
          <li
            v-for="point in [...shown.points].reverse()"
            :key="point.day"
            class="flex justify-between border-b border-line py-1"
          >
            <span>{{ formatFullDay(point.day) }}</span>
            <span>{{ formatCm(point.cm) }}</span>
          </li>
        </ul>
      </template>
    </UiLineChart>
    <p
      v-else-if="shown"
      class="text-muted"
    >
      {{ summary }}
    </p>
  </div>
</template>
