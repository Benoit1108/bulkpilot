<script setup lang="ts">
import { formatDayIn, shiftDay, WEEKDAY_LETTERS, weekStart } from '~/services/calendar/days'

/**
 * The week of the day shown, Monday first: each day one tap away, a dot under the days that hold
 * something (meals logged), the day shown filled, today in the accent. A day to come cannot be
 * picked.
 */
const props = defineProps<{
  day: string
  today: string
  marked: ReadonlySet<string>
  /** Said after a marked day (« repas saisis »). */
  markLabel: string
}>()
const emit = defineEmits<{ pick: [day: string] }>()

const days = computed(() => Array.from({ length: 7 }, (_, i) => {
  const date = shiftDay(weekStart(props.day), i)
  return { date, letter: WEEKDAY_LETTERS[i]!, number: Number(date.slice(8)), shown: date === props.day, today: date === props.today, marked: props.marked.has(date), future: date > props.today }
}))
const label = (day: (typeof days.value)[number]) => `${formatDayIn(day.date, props.today)}${day.today ? ', aujourd’hui' : ''}${day.marked ? `, ${props.markLabel}` : ''}`
function pick(day: (typeof days.value)[number]): void {
  if (!day.future && !day.shown) emit('pick', day.date)
}
</script>

<template>
  <!-- Seven columns: on the narrowest phones a day is a little under 44px wide (the arrows beside
       give the same moves at full size), hence the exemption from the target size check. -->
  <ol
    class="grid grid-cols-7 gap-1"
    role="list"
    aria-label="Jours de la semaine"
    data-tap-exempt
  >
    <li
      v-for="cell in days"
      :key="cell.date"
    >
      <!-- A day to come stays focusable (aria-disabled): the focus never drops on the way. -->
      <button
        type="button"
        class="flex min-h-tap w-full cursor-pointer flex-col items-center gap-0.5 rounded-md border-0 bg-transparent py-1 text-inherit hover:bg-surface-raised aria-disabled:cursor-not-allowed aria-disabled:hover:bg-transparent"
        :aria-label="label(cell)"
        :aria-current="cell.shown ? 'date' : undefined"
        :aria-disabled="cell.future || undefined"
        @click="pick(cell)"
      >
        <span
          class="text-xs text-muted"
          aria-hidden="true"
        >{{ cell.letter }}</span>
        <span
          class="grid size-8 place-items-center rounded-full text-base tabular-nums"
          :class="cell.shown ? 'bg-accent-fill font-semibold text-accent-contrast' : cell.today ? 'font-bold text-accent-ink' : cell.future ? 'text-muted' : ''"
          aria-hidden="true"
        >{{ cell.number }}</span>
        <span
          class="size-1.5 rounded-full"
          :class="cell.marked ? 'bg-accent' : 'bg-transparent'"
          aria-hidden="true"
        />
      </button>
    </li>
  </ol>
</template>
