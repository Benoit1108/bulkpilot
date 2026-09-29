<script setup lang="ts">
import type { ChartSeries } from '~/components/ui/LineChart.vue'
import { DATA_TABLE } from '~/components/ui/classes'
import { formatShortDay, formatFullDay, localDay, parseDay, shiftDay } from '~/services/calendar/days'
import { formatNumber } from '~/services/text/numbers'
import type { components } from '~/types/api'
import { formatKg, formatSignedKg } from '../format'
import type { WeightSummary } from '../progression'

/**
 * Mean calories against mean weight, week by week (spec §56): two charts on the same weeks
 * rather than one with two scales (ADR-0026), then the table of both. The week under way is
 * marked and left out of the comparison; how many days and weigh-ins make each mean is shown.
 */
const props = defineProps<{ weights: WeightSummary['weeks'], intakes: components['schemas']['NutritionHistory']['weeks'] }>()

const kcal = (value: number) => `${formatNumber(value, 0)}\u00A0kcal`
const today = localDay()
/** The Monday of this week, as the API keys weeks. */
const thisWeek = shiftDay(today, -((parseDay(today).getDay() + 6) % 7))
const weeks = computed(() => [...new Set([...props.weights.map(week => week.week), ...props.intakes.map(week => week.week)])].sort())
const domain = computed<[string, string] | undefined>(() => (weeks.value.length ? [weeks.value[0]!, weeks.value.at(-1)!] : undefined))
const rows = computed(() => weeks.value.map((week) => {
  const intake = props.intakes.find(one => one.week === week)
  const weight = props.weights.find(one => one.week === week)
  return { week, current: week === thisWeek, kcal: intake?.averageEnergyKcal ?? null, days: intake?.days ?? 0, kg: weight?.averageKg ?? null, weighIns: weight?.weighIns ?? 0 }
}))

const energy = computed<ChartSeries[]>(() => [{ name: 'Calories moyennes', variant: 'line', tone: 'accent', points: props.intakes.map(week => ({ x: week.week, y: week.averageEnergyKcal })) }])
const weight = computed<ChartSeries[]>(() => [{ name: 'Poids moyen', variant: 'line', tone: 'ink', points: props.weights.map(week => ({ x: week.week, y: week.averageKg })) }])

const both = computed(() => rows.value.filter(row => !row.current && row.kcal !== null && row.kg !== null))
const summary = computed(() => {
  const [first, last] = [both.value[0], both.value.at(-1)]
  if (!first || !last || first === last) return 'Il faut au moins deux semaines terminées avec des repas saisis et des pesées pour comparer.'
  return `De la semaine du ${formatFullDay(first.week)} à celle du ${formatFullDay(last.week)}\u00A0: ${kcal(first.kcal!)} puis ${kcal(last.kcal!)} par jour, et un poids moyen de ${formatKg(first.kg!)} à ${formatKg(last.kg!)} (${formatSignedKg(last.kg! - first.kg!)}).`
})
const weekLabel = (week: string) => `sem. du ${formatShortDay(week)}`
const weeksCount = (count: number) => `${count}\u00A0semaine${count > 1 ? 's' : ''}`
const energySummary = computed(() => `Moyenne des jours saisis de chaque semaine, ${weeksCount(props.intakes.length)}.`)
const weightSummary = computed(() => `Moyenne des pesées de chaque semaine, ${weeksCount(props.weights.length)}.`)
</script>

<template>
  <div class="flex flex-col gap-4">
    <p>{{ summary }}</p>
    <UiLineChart
      v-if="intakes.length"
      title="Calories moyennes par semaine"
      :summary="energySummary"
      :series="energy"
      :format-y="kcal"
      :format-x="weekLabel"
      :x-domain="domain"
      :height="160"
    >
      <template #table>
        <p class="mt-2 text-muted">
          Les valeurs des deux graphiques sont dans le tableau qui les suit.
        </p>
      </template>
    </UiLineChart>
    <UiLineChart
      v-if="weights.length"
      title="Poids moyen par semaine"
      :summary="weightSummary"
      :series="weight"
      :format-y="formatKg"
      :format-x="weekLabel"
      :x-domain="domain"
      :height="160"
    >
      <template #table>
        <p class="mt-2 text-muted">
          Les valeurs des deux graphiques sont dans le tableau qui les suit.
        </p>
      </template>
    </UiLineChart>
    <details
      v-if="rows.length"
      class="text-sm"
    >
      <summary class="min-h-tap cursor-pointer content-center font-semibold text-accent-ink">
        Voir les semaines côte à côte
      </summary>
      <table :class="DATA_TABLE">
        <thead>
          <tr>
            <th scope="col">
              Semaine
            </th>
            <th scope="col">
              Calories / jour
            </th>
            <th scope="col">
              Jours saisis
            </th>
            <th scope="col">
              Poids moyen
            </th>
            <th scope="col">
              Pesées
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in [...rows].reverse()"
            :key="row.week"
          >
            <th
              scope="row"
              class="font-normal"
            >
              {{ weekLabel(row.week) }}{{ row.current ? ' (en cours)' : '' }}
            </th>
            <td>{{ row.kcal === null ? '—' : kcal(row.kcal) }}</td>
            <td>{{ row.days }}</td>
            <td>{{ row.kg === null ? '—' : formatKg(row.kg) }}</td>
            <td>{{ row.weighIns }}</td>
          </tr>
        </tbody>
      </table>
    </details>
  </div>
</template>
