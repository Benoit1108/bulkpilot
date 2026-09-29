<script setup lang="ts">
import type { ChartSeries } from '~/components/ui/LineChart.vue'
import { DATA_TABLE } from '~/components/ui/classes'
import { formatShortDay, localDay } from '~/services/calendar/days'
import { formatNumber } from '~/services/text/numbers'
import type { components } from '~/types/api'
import type { Measurement } from '~/features/training/training'

/**
 * The strength of an exercise session after session (spec §55, §57): the estimated 1RM and the
 * heaviest load for a weighted exercise, the best reps or hold otherwise. Two sessions may share
 * a day: the table has a row for each.
 */
const props = defineProps<{ progress: components['schemas']['ExerciseProgress'], measurement: Measurement }>()

type Session = (typeof props.progress)['sessions'][number]
const day = (session: Session) => localDay(new Date(session.date))
const line = (name: string, variant: ChartSeries['variant'], tone: ChartSeries['tone'], value: (session: Session) => number | null): ChartSeries => ({
  name, variant, tone,
  points: props.progress.sessions.flatMap((session) => {
    const y = value(session)
    return y === null ? [] : [{ x: day(session), y }]
  }),
})
const weighted = computed(() => props.measurement === 'weight')
const series = computed<ChartSeries[]>(() => {
  if (props.measurement === 'duration') return [line('Meilleur maintien', 'line', 'accent', session => session.bestSeconds)]
  if (props.measurement === 'bodyweight') return [line('Meilleures reps', 'line', 'accent', session => session.bestReps)]
  return [line('1RM estimé', 'line', 'accent', session => session.estimatedOneRepMax), line('Charge max', 'dashed', 'ink', session => session.topLoadKg)]
})
const unit = computed(() => ({ duration: '\u00A0s', bodyweight: '\u00A0reps', weight: '\u00A0kg' }[props.measurement]))
const format = (value: number) => `${formatNumber(value, 1)}${unit.value}`
const shown = computed(() => series.value[0]!.points)
const main = (session: Session) => ({ duration: session.bestSeconds, bodyweight: session.bestReps, weight: session.estimatedOneRepMax }[props.measurement])
const rows = computed(() => props.progress.sessions
  .filter(session => main(session) !== null)
  .map(session => ({ key: session.workoutId, day: day(session), main: main(session)!, top: session.topLoadKg }))
  .reverse())
const summary = computed(() => {
  const [first, last] = [shown.value[0], shown.value.at(-1)]
  if (!first || !last) return ''
  return first === last
    ? `Une séance\u00A0: ${format(last.y)}.`
    : `${series.value[0]!.name}\u00A0: de ${format(first.y)} à ${format(last.y)} sur ${shown.value.length} séances.`
})
</script>

<template>
  <UiLineChart
    v-if="shown.length"
    title="Force"
    :summary="summary"
    :series="series"
    :format-y="format"
    :format-x="formatShortDay"
  >
    <template #table>
      <table :class="DATA_TABLE">
        <thead>
          <tr>
            <th scope="col">
              Séance
            </th>
            <th scope="col">
              {{ series[0]!.name }}
            </th>
            <th
              v-if="weighted"
              scope="col"
            >
              Charge max
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.key"
          >
            <th
              scope="row"
              class="font-normal"
            >
              {{ formatShortDay(row.day) }}
            </th>
            <td>{{ format(row.main) }}</td>
            <td v-if="weighted">
              {{ row.top === null ? '—' : format(row.top) }}
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </UiLineChart>
</template>
