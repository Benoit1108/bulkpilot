<script setup lang="ts">
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import type { RingValue } from '~/components/ui/Ring.vue'
import MacroBars from '~/features/nutrition/components/MacroBars.vue'
import { formatKcal } from '~/features/nutrition/format'
import type { Nutrients, NutritionAverages, NutritionDay } from '~/features/nutrition/nutrition'
import { plural } from '~/services/text/plural'
import { viewDays, viewLong, type DashboardView } from '../dashboard'

/**
 * Calories and macros (spec §13): what was eaten today against the target, or the daily
 * average of the days logged over the week or month. The server adds them up.
 */
const props = defineProps<{ view: DashboardView, today: string }>()

const api = useApi()
const daily = computed(() => props.view === 'day')
const days = computed(() => viewDays(props.view))
// The same keys as the journal and its averages: whichever loads first serves the other.
const { data, status, error, refresh } = useAsyncData<NutritionDay | NutritionAverages>(
  () => (daily.value ? NUTRITION_KEYS.day(props.today) : NUTRITION_KEYS.averages(days.value, props.today)),
  () => (daily.value
    ? api.get('/api/nutrition/days/{day}', { path: { day: props.today } })
    : api.get('/api/nutrition/averages', { query: { days: days.value, today: props.today } })),
)
// Past midnight, the day before is kept until today's arrives: it is not shown as today's.
const day = computed(() => (data.value && 'meals' in data.value && data.value.day === props.today ? data.value : null))
const averages = computed(() => (data.value && 'loggedDays' in data.value ? data.value : null))

const shown = computed<{ totals: Nutrients, target: NutritionDay['target'] } | null>(() => {
  if (daily.value) return day.value ? { totals: day.value.totals, target: day.value.target } : null
  const average = averages.value?.dailyAverage
  return average ? { totals: average, target: averages.value?.currentTarget ?? null } : null
})
const loading = computed(() => status.value === 'pending' && !(daily.value ? day.value : averages.value))
const failed = computed(() => Boolean(error.value) && !(daily.value ? day.value : averages.value))
const emptyText = computed(() => (daily.value ? 'Rien de saisi aujourd’hui.' : `Aucun repas saisi ${viewLong(props.view)}.`))
const nothing = computed(() => !loading.value && (!shown.value || (daily.value && shown.value.totals.energyKcal === 0)))

/** What is left of the target (or how much over it): the figure the day is read by. */
const left = computed(() => (shown.value?.target ? Math.round(shown.value.target.energyKcal - shown.value.totals.energyKcal) : null))
const rings = computed<RingValue[]>(() => {
  const value = shown.value
  if (!value?.target) return []
  return [
    { value: value.totals.energyKcal, max: value.target.energyKcal, tone: 'accent' },
    { value: value.totals.protein, max: value.target.proteinGrams, tone: 'protein' },
    { value: value.totals.carbohydrate, max: value.target.carbohydrateGrams, tone: 'carbs' },
    { value: value.totals.fat, max: value.target.fatGrams, tone: 'fat' },
  ]
})
</script>

<template>
  <UiTile
    title="Nutrition"
    to="/nutrition"
    :loading="loading"
    :failed="failed"
    @retry="refresh()"
  >
    <div
      v-if="nothing"
      class="flex flex-col gap-2"
    >
      <p class="text-sm text-muted">
        {{ emptyText }}
      </p>
      <UiLinkButton
        :to="{ path: '/nutrition', query: { ajouter: 'aliment' } }"
        variant="secondary"
      >
        Ajouter un aliment
      </UiLinkButton>
    </div>
    <template v-else-if="shown">
      <p
        v-if="!daily && averages"
        class="text-sm text-muted"
      >
        Moyenne par jour, sur {{ averages.loggedDays }} {{ plural(averages.loggedDays, 'jour') }} {{ plural(averages.loggedDays, 'saisi') }} {{ viewLong(view) }}&nbsp;:
      </p>
      <div class="flex flex-wrap items-center gap-4">
        <!-- The figures are in the text beside it and in the bars below: the rings only draw them. -->
        <UiRing
          v-if="rings.length"
          :rings="rings"
          class="size-20 sm:size-24 md:size-28"
        />
        <p
          v-if="left !== null"
          class="flex min-w-0 flex-col"
        >
          <span class="text-sm text-muted">{{ left >= 0 ? (daily ? 'Restantes' : 'Sous l’objectif') : 'Au-dessus de l’objectif' }}</span>
          <span class="text-2xl font-bold tabular-nums sm:text-figure">{{ formatKcal(Math.abs(left)) }}</span>
          <span class="text-sm text-muted">{{ formatKcal(shown.totals.energyKcal) }} sur {{ formatKcal(shown.target!.energyKcal) }}</span>
        </p>
        <p
          v-else
          class="text-figure font-bold tabular-nums"
        >
          {{ formatKcal(shown.totals.energyKcal) }}
        </p>
      </div>
      <MacroBars
        :protein="shown.totals.protein"
        :carbohydrate="shown.totals.carbohydrate"
        :fat="shown.totals.fat"
        :target="shown.target"
      />
      <div class="flex flex-wrap gap-2">
        <UiLinkButton
          v-if="daily"
          :to="{ path: '/nutrition', query: { ajouter: 'aliment' } }"
          variant="secondary"
        >
          Ajouter un aliment
        </UiLinkButton>
        <UiLinkButton
          v-if="!shown.target"
          to="/nutrition/objectifs"
          variant="secondary"
        >
          Fixer mon objectif
        </UiLinkButton>
      </div>
    </template>
  </UiTile>
</template>
