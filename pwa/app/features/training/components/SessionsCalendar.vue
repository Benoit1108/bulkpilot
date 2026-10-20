<script setup lang="ts">
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { formatLongMonth, localDay, monthStart, shiftMonth } from '~/services/calendar/days'
import { plural } from '~/services/text/plural'

/**
 * The month's sessions on a calendar: the rhythm at a glance, instead of a list of dates. One page of
 * history (the 50 latest before the month's end) covers a month; past 50 sessions in a month, its
 * first days would be left out.
 */
const props = defineProps<{ today: string }>()

const api = useApi()
const month = ref(props.today)
const first = computed(() => monthStart(month.value))
/** Midnight, local time, at the start of the next month. */
const end = computed(() => new Date(`${shiftMonth(first.value, 1)}T00:00:00`))
const { data: workouts, error, refresh } = useAsyncData(
  () => TRAINING_KEYS.month(first.value),
  () => api.get('/api/workouts', { query: { limit: 50, before: end.value.toISOString() } }),
  { watch: [first] },
)
const inMonth = computed(() => (workouts.value ?? []).map(workout => localDay(new Date(workout.startedAt))).filter(day => monthStart(day) === first.value))
const days = computed(() => new Set(inMonth.value))
const count = computed(() => inMonth.value.length)
</script>

<template>
  <UiCard title="Séances du mois">
    <UiLoadError
      v-if="error && !workouts"
      :focus-on-mount="false"
      @retry="refresh()"
    />
    <template v-else>
      <UiMonthCalendar
        v-model:month="month"
        :marked="days"
        mark-label="séance"
        :today="today"
      />
      <p
        v-if="workouts"
        class="text-sm text-muted"
      >
        {{ count }} {{ plural(count, 'séance') }} en {{ formatLongMonth(first) }}
      </p>
    </template>
  </UiCard>
</template>
