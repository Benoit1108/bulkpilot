<script setup lang="ts">
import type { ChartSeries } from '~/components/ui/LineChart.vue'
import { formatNumber } from '~/services/text/numbers'
import type { components } from '~/types/api'
import { TEXT_LINK, DATA_TABLE } from '~/components/ui/classes'
import { endedWeeks, weekLabel, WEEK_UNDER_WAY } from '../format'

/**
 * The training of the period (spec §57): sessions and their frequency, working sets, reps,
 * tonnage and records beaten, and the tonnage week by week. The week under way is in the table
 * only: its few sessions would make the line fall.
 */
const props = defineProps<{ analytics: components['schemas']['TrainingAnalytics'], during: string, thisWeek: string }>()

const tonnage = (kg: number) => `${formatNumber(kg, 0)}\u00A0kg`
const figures = computed(() => {
  const { totals, sessionsPerWeek } = props.analytics
  return [
    { label: 'Séances', value: String(totals.sessions) },
    { label: 'Séances par semaine', value: sessionsPerWeek === null ? '—' : formatNumber(sessionsPerWeek, 1) },
    { label: 'Séries de travail', value: String(totals.workingSets) },
    { label: 'Répétitions', value: formatNumber(totals.reps, 0) },
    { label: 'Tonnage', value: tonnage(totals.tonnageKg) },
    { label: 'Records battus', value: String(totals.records) },
  ]
})
const series = computed<ChartSeries[]>(() => [{ name: 'Tonnage', variant: 'line', tone: 'accent', points: endedWeeks(props.analytics.weeks, props.thisWeek).map(week => ({ x: week.week, y: week.tonnageKg })) }])
const label = (week: string) => weekLabel(week, props.thisWeek)
/** Said only when the week under way has sessions the line leaves out. */
const underWay = computed(() => props.analytics.weeks.some(week => week.week === props.thisWeek && week.sessions > 0))
const summary = computed(() => `${tonnage(props.analytics.totals.tonnageKg)} soulevés en ${props.analytics.totals.sessions} séance${props.analytics.totals.sessions > 1 ? 's' : ''} ${props.during}.${underWay.value ? ` ${WEEK_UNDER_WAY}` : ''}`)
</script>

<template>
  <div class="flex flex-col gap-4">
    <dl class="m-0 grid grid-cols-2 gap-3 sm:grid-cols-3">
      <div
        v-for="figure in figures"
        :key="figure.label"
      >
        <dt class="text-sm text-muted">
          {{ figure.label }}
        </dt>
        <dd class="m-0 text-lg font-semibold tabular-nums">
          {{ figure.value }}
        </dd>
      </div>
    </dl>
    <UiLineChart
      v-if="analytics.totals.sessions"
      title="Tonnage par semaine"
      :summary="summary"
      :series="series"
      :format-y="tonnage"
      :format-x="label"
    >
      <template #table>
        <table :class="DATA_TABLE">
          <thead>
            <tr>
              <th scope="col">
                Semaine
              </th>
              <th scope="col">
                Séances
              </th>
              <th scope="col">
                Tonnage
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="week in [...analytics.weeks].reverse()"
              :key="week.week"
            >
              <th
                scope="row"
                class="font-normal"
              >
                {{ label(week.week) }}
              </th>
              <td>{{ week.sessions }}</td>
              <td>{{ tonnage(week.tonnageKg) }}</td>
            </tr>
          </tbody>
        </table>
      </template>
    </UiLineChart>
    <NuxtLink
      to="/training/exercices"
      :class="TEXT_LINK"
    >
      La force et les records de chaque exercice
    </NuxtLink>
  </div>
</template>
