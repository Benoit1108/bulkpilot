<script setup lang="ts">
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal, formatMacro } from '~/features/nutrition/format'
import type { Nutrients, NutritionAverages, NutritionDay } from '~/features/nutrition/nutrition'
import { plural } from '~/services/text/plural'
import { viewDays, viewLong, type DashboardView } from '../dashboard'
import DashboardWidget from './DashboardWidget.vue'

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

const macros = computed(() => {
  const value = shown.value
  if (!value) return []
  return [
    { label: 'Protéines', eaten: value.totals.protein, target: value.target?.proteinGrams ?? null },
    { label: 'Glucides', eaten: value.totals.carbohydrate, target: value.target?.carbohydrateGrams ?? null },
    { label: 'Lipides', eaten: value.totals.fat, target: value.target?.fatGrams ?? null },
  ]
})
const macroText = (eaten: number, target: number | null) => (target === null ? formatMacro(eaten) : `${formatMacro(eaten)} sur ${formatMacro(target)}`)
</script>

<template>
  <DashboardWidget
    title="Nutrition"
    to="/nutrition"
    link-label="Journal"
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
      <p v-if="!daily && averages">
        Moyenne par jour, sur {{ averages.loggedDays }} {{ plural(averages.loggedDays, 'jour') }} {{ plural(averages.loggedDays, 'saisi') }} {{ viewLong(view) }}&nbsp;:
      </p>
      <UiProgressBar
        v-if="shown.target"
        label="Calories"
        :value="shown.totals.energyKcal"
        :max="shown.target.energyKcal"
        :value-text="`${formatKcal(shown.totals.energyKcal)} sur ${formatKcal(shown.target.energyKcal)}`"
      />
      <p>
        <span class="text-2xl font-bold tabular-nums">{{ formatKcal(shown.totals.energyKcal) }}</span>
        <span
          v-if="shown.target"
          class="text-muted"
        > sur {{ formatKcal(shown.target.energyKcal) }}</span>
      </p>
      <dl class="m-0 grid grid-cols-3 gap-2 text-sm">
        <div
          v-for="macro in macros"
          :key="macro.label"
        >
          <dt class="text-muted">
            {{ macro.label }}
          </dt>
          <dd class="m-0 tabular-nums">
            {{ macroText(macro.eaten, macro.target) }}
          </dd>
        </div>
      </dl>
      <UiLinkButton
        v-if="!shown.target"
        to="/nutrition/objectifs"
        variant="secondary"
        class="self-start"
      >
        Fixer mon objectif
      </UiLinkButton>
    </template>
  </DashboardWidget>
</template>
