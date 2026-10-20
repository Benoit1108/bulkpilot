<script setup lang="ts">
import { formatKcal } from '~/features/nutrition/format'
import { formatDayIn, WEEKDAY_LETTERS } from '~/services/calendar/days'

/**
 * The week's calories, a bar a day against the target (the dashed line): the rhythm of the week at a
 * glance, MacroFactor-style. The day under way is lighter, a day without entry a stub. The bars are an
 * image; the table under them says the same in words. The week's means are the averages card's.
 */
const props = defineProps<{
  days: ReadonlyArray<{ day: string, energyKcal: number | null }>
  target: number | null
  today: string
}>()

const top = computed(() => Math.max(props.target ?? 0, ...props.days.map(day => day.energyKcal ?? 0), 1))
const bars = computed(() => props.days.map((day, i) => ({
  ...day,
  letter: WEEKDAY_LETTERS[i]!,
  said: day.day > props.today ? 'à venir' : day.energyKcal ? `${formatKcal(day.energyKcal)}${day.day === props.today ? ', en cours' : ''}` : 'rien de saisi',
  height: `${Math.round(((day.energyKcal ?? 0) / top.value) * 100)}%`,
  fill: day.energyKcal === null || day.energyKcal === 0 ? 'bg-line' : day.day === props.today ? 'bg-accent/50' : 'bg-accent',
})))
const targetLine = computed(() => (props.target ? `${Math.round((props.target / top.value) * 100)}%` : null))
</script>

<template>
  <UiCard title="Cette semaine">
    <p
      v-if="target"
      class="text-sm text-muted"
    >
      Pointillé&nbsp;: {{ formatKcal(target) }} visées par jour
    </p>
    <div
      class="relative flex h-28 items-end gap-1.5"
      aria-hidden="true"
    >
      <span
        v-if="targetLine"
        class="absolute inset-x-0 border-t border-dashed border-muted"
        :style="{ bottom: targetLine }"
      />
      <span
        v-for="bar in bars"
        :key="bar.day"
        class="min-h-1 flex-1 rounded-sm"
        :class="bar.fill"
        :style="{ height: bar.height }"
      />
    </div>
    <div
      class="flex gap-1.5 text-center text-xs text-muted"
      aria-hidden="true"
    >
      <span
        v-for="bar in bars"
        :key="bar.day"
        class="flex-1"
      >{{ bar.letter }}</span>
    </div>
    <!-- A table ignores the width sr-only gives it: the wrapper clips it. -->
    <div class="sr-only">
      <table>
        <caption>Calories de chaque jour de la semaine</caption>
        <tbody>
          <tr
            v-for="bar in bars"
            :key="bar.day"
          >
            <th scope="row">
              {{ formatDayIn(bar.day, today) }}
            </th>
            <td>{{ bar.said }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </UiCard>
</template>
