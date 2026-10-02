<script setup lang="ts">
import type { ChartMark, ChartSeries } from '~/components/ui/LineChart.vue'
import { DATA_TABLE } from '~/components/ui/classes'
import { formatShortDay } from '~/services/calendar/days'
import type { components } from '~/types/api'
import { formatNumber } from '~/services/text/numbers'

type History = components['schemas']['NutritionHistory']

/**
 * Calories and proteins day by day over the period (spec §55), against today's targets: the
 * days logged only, a day without entry is not a day at zero. Past two months the days turn to
 * dots under the line of the weekly means, and the table lists the weeks.
 */
const props = defineProps<{ history: History, during: string }>()

/** Beyond this many days logged, a line through every day is a scribble. */
const DAILY_LIMIT = 62
/** Beyond about six months, the days' dots hide the weekly line: the weeks alone are drawn. */
const DOTS_LIMIT = 183

const kcal = (value: number) => `${formatNumber(value, 0)}\u00A0kcal`
const grams = (value: number) => `${formatNumber(value, 0)}\u00A0g`
const weekLabel = (week: string) => `sem. du ${formatShortDay(week)}`
const logged = computed(() => props.history.days.length)
const weekly = computed(() => logged.value > DAILY_LIMIT)

interface Nutrient {
  title: string
  /** The table's heading for the weekly means. */
  perDay: string
  format: (value: number) => string
  day: (day: History['days'][number]) => number
  week: (week: History['weeks'][number]) => number
  target: (target: NonNullable<History['target']>) => number
  average: number | null
}
const nutrients = computed<Nutrient[]>(() => [
  { title: 'Calories', perDay: 'Calories / jour', format: kcal, day: day => day.energyKcal, week: week => week.averageEnergyKcal, target: target => target.energyKcal, average: props.history.averageEnergyKcal },
  { title: 'Protéines', perDay: 'Protéines / jour', format: grams, day: day => day.proteinGrams, week: week => week.averageProteinGrams, target: target => target.proteinGrams, average: props.history.averageProteinGrams },
])

function series(nutrient: Nutrient): ChartSeries[] {
  const days = props.history.days.map(day => ({ x: day.day, y: nutrient.day(day) }))
  if (!weekly.value) return [{ name: nutrient.title, variant: 'line', tone: 'accent', points: days }]
  const weeks: ChartSeries = { name: 'Moyenne de la semaine', variant: 'line', tone: 'accent', points: props.history.weeks.map(week => ({ x: week.week, y: nutrient.week(week) })) }
  return logged.value > DOTS_LIMIT ? [weeks] : [weeks, { name: 'Chaque jour', variant: 'dots', tone: 'muted', points: days }]
}
const marks = (nutrient: Nutrient): ChartMark[] => (props.history.target ? [{ y: nutrient.target(props.history.target), label: `Objectif ${nutrient.format(nutrient.target(props.history.target))}` }] : [])
const summary = (nutrient: Nutrient) => (nutrient.average === null
  ? `Aucun jour saisi ${props.during}.`
  : `${nutrient.format(nutrient.average)} par jour en moyenne ${props.during}, sur ${logged.value} jour${logged.value > 1 ? 's' : ''} saisi${logged.value > 1 ? 's' : ''}.`)
const rows = (nutrient: Nutrient) => (weekly.value
  ? props.history.weeks.map(week => ({ key: week.week, label: weekLabel(week.week), value: nutrient.format(nutrient.week(week)), days: week.days }))
  : props.history.days.map(day => ({ key: day.day, label: formatShortDay(day.day), value: nutrient.format(nutrient.day(day)), days: null }))).reverse()
</script>

<template>
  <div class="flex flex-col gap-6">
    <template v-if="logged">
      <UiLineChart
        v-for="nutrient in nutrients"
        :key="nutrient.title"
        :title="nutrient.title"
        :summary="summary(nutrient)"
        :series="series(nutrient)"
        :marks="marks(nutrient)"
        :format-y="nutrient.format"
        :format-x="formatShortDay"
      >
        <template #table>
          <table :class="DATA_TABLE">
            <thead>
              <tr>
                <th scope="col">
                  {{ weekly ? 'Semaine' : 'Jour' }}
                </th>
                <th scope="col">
                  {{ weekly ? nutrient.perDay : nutrient.title }}
                </th>
                <th
                  v-if="weekly"
                  scope="col"
                >
                  Jours saisis
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows(nutrient)"
                :key="row.key"
              >
                <th
                  scope="row"
                  class="font-normal"
                >
                  {{ row.label }}
                </th>
                <td>{{ row.value }}</td>
                <td v-if="weekly">
                  {{ row.days }}
                </td>
              </tr>
            </tbody>
          </table>
        </template>
      </UiLineChart>
    </template>
    <p
      v-else
      class="text-muted"
    >
      {{ summary(nutrients[0]!) }}
    </p>
  </div>
</template>
