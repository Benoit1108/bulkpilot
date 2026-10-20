<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { formatLongDay, formatLongMonth, monthStart, parseDay, shiftDay, shiftMonth } from '~/services/calendar/days'

/**
 * A month, Monday first, its marked days filled (a session done, a day logged): a table, so a
 * screen reader can walk it by week and day, each marked day saying what it holds. Earlier and
 * later months are a button away; the future is not offered.
 */
const props = defineProps<{
  /** Any day of the month shown. */
  month: string
  marked: ReadonlySet<string>
  /** Said after a marked day (« séance »). */
  markLabel: string
  today: string
}>()
const emit = defineEmits<{ 'update:month': [month: string] }>()

const WEEKDAYS = [['L', 'lundi'], ['M', 'mardi'], ['M', 'mercredi'], ['J', 'jeudi'], ['V', 'vendredi'], ['S', 'samedi'], ['D', 'dimanche']] as const
const first = computed(() => monthStart(props.month))
const weeks = computed(() => {
  const start = parseDay(first.value)
  const lead = (start.getDay() + 6) % 7
  const days = new Date(start.getFullYear(), start.getMonth() + 1, 0).getDate()
  const cells: ({ day: string, number: number, marked: boolean, today: boolean, future: boolean } | null)[] = Array.from({ length: lead }, () => null)
  for (let n = 1; n <= days; n++) {
    const day = shiftDay(first.value, n - 1)
    cells.push({ day, number: n, marked: props.marked.has(day), today: day === props.today, future: day > props.today })
  }
  while (cells.length % 7) cells.push(null)
  return Array.from({ length: cells.length / 7 }, (_, w) => cells.slice(w * 7, w * 7 + 7))
})
const isCurrent = computed(() => first.value === monthStart(props.today))
// The button stays focusable when there is no later month (a disabled one would drop the focus).
function later(): void {
  if (!isCurrent.value) emit('update:month', shiftMonth(first.value, 1))
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <div class="flex items-center justify-between gap-2">
      <p
        class="text-lg font-semibold first-letter:uppercase"
        aria-live="polite"
      >
        {{ formatLongMonth(first) }}
      </p>
      <span class="flex gap-1">
        <button
          type="button"
          class="grid size-tap cursor-pointer place-items-center rounded-full text-accent-ink hover:bg-surface-raised"
          aria-label="Mois précédent"
          @click="emit('update:month', shiftMonth(first, -1))"
        >
          <ChevronLeft
            :size="20"
            aria-hidden="true"
          />
        </button>
        <button
          type="button"
          class="grid size-tap cursor-pointer place-items-center rounded-full text-accent-ink hover:bg-surface-raised aria-disabled:cursor-not-allowed aria-disabled:opacity-40"
          aria-label="Mois suivant"
          :aria-disabled="isCurrent || undefined"
          @click="later"
        >
          <ChevronRight
            :size="20"
            aria-hidden="true"
          />
        </button>
      </span>
    </div>
    <table class="w-full table-fixed border-collapse text-center tabular-nums">
      <caption class="sr-only">
        {{ formatLongMonth(first) }}
      </caption>
      <thead>
        <tr>
          <th
            v-for="[short, long] in WEEKDAYS"
            :key="long"
            scope="col"
            class="pb-1 text-xs font-normal text-muted"
          >
            <span aria-hidden="true">{{ short }}</span>
            <span class="sr-only">{{ long }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(week, w) in weeks"
          :key="w"
        >
          <td
            v-for="(cell, d) in week"
            :key="d"
            class="p-0.5"
          >
            <span
              v-if="cell"
              class="mx-auto grid size-8 place-items-center rounded-full text-sm sm:size-9"
              :class="[cell.marked ? 'bg-accent-fill font-semibold text-accent-contrast' : cell.future ? 'text-muted' : '', { 'outline-2 outline-offset-2 outline-accent': cell.today }]"
              :aria-current="cell.today ? 'date' : undefined"
            >
              <span aria-hidden="true">{{ cell.number }}</span>
              <span class="sr-only">{{ formatLongDay(cell.day) }}<template v-if="cell.marked">, {{ markLabel }}</template></span>
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
