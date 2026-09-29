<script setup lang="ts">
import type { ChartMark, ChartSeries } from '~/components/ui/LineChart.vue'
import { formatFullDay, formatShortDay } from '~/services/calendar/days'
import { formatKg, formatSignedKg, periodLong, periodLongStart } from '../format'
import type { Period, WeightSummary } from '../progression'
import PeriodPicker from './PeriodPicker.vue'

/**
 * The weight over a period (spec §55): each weigh-in, the 7-day and 30-day averages, and the
 * goal's next step and target as lines to reach.
 */
const props = defineProps<{ summary: WeightSummary }>()
const period = defineModel<Period>('period', { required: true })

const points = computed(() => props.summary.points)
const series = computed<ChartSeries[]>(() => [
  { name: 'Pesées', variant: 'dots', tone: 'muted', points: points.value.map(point => ({ x: point.day, y: point.weightKg })) },
  { name: 'Moyenne 7 jours', variant: 'line', tone: 'accent', points: points.value.map(point => ({ x: point.day, y: point.average7Kg })) },
  { name: 'Moyenne 30 jours', variant: 'dashed', tone: 'ink', points: points.value.map(point => ({ x: point.day, y: point.average30Kg })) },
])
const marks = computed<ChartMark[]>(() => {
  const goal = props.summary.goal
  if (!goal) return []
  const next = goal.next && !goal.next.target ? [{ y: goal.next.weightKg, label: `Jalon ${formatKg(goal.next.weightKg)}` }] : []
  return [...next, { y: goal.targetKg, label: `Objectif ${formatKg(goal.targetKg)}` }]
})

const during = computed(() => periodLong(period.value))
const summaryText = computed(() => {
  const first = points.value[0]
  const last = points.value.at(-1)
  if (!first || !last) return `Aucune pesée ${during.value}.`
  if (first === last) return `Une pesée ${during.value}\u00A0: ${formatKg(last.weightKg)}.`
  return `${periodLongStart(period.value)}, la moyenne 7 jours passe de ${formatKg(first.average7Kg)} à ${formatKg(last.average7Kg)} (${formatSignedKg(last.average7Kg - first.average7Kg)}), sur ${points.value.length} pesées.`
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <PeriodPicker
      v-model="period"
      name="weight-period"
    />
    <UiLineChart
      v-if="points.length"
      title="Poids"
      :summary="summaryText"
      :series="series"
      :marks="marks"
      :format-y="formatKg"
      :format-x="formatShortDay"
    >
      <template #table>
        <table class="mt-2 w-full border-collapse tabular-nums [&_:is(td,th)]:border-b [&_:is(td,th)]:border-line [&_:is(td,th)]:py-1 [&_:is(td,th)]:text-right [&_:is(td,th):first-child]:text-left">
          <thead>
            <tr>
              <th scope="col">
                Jour
              </th>
              <th scope="col">
                Pesée
              </th>
              <th scope="col">
                Moy. 7 j
              </th>
              <th scope="col">
                Moy. 30 j
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="point in [...points].reverse()"
              :key="point.day"
            >
              <th
                scope="row"
                class="font-normal"
              >
                {{ formatFullDay(point.day) }}
              </th>
              <td>{{ formatKg(point.weightKg) }}</td>
              <td>{{ formatKg(point.average7Kg) }}</td>
              <td>{{ formatKg(point.average30Kg) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </UiLineChart>
    <p
      v-else
      class="text-muted"
    >
      {{ summaryText }}
    </p>
  </div>
</template>
