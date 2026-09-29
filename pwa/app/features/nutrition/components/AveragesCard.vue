<script setup lang="ts">
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal, formatMacro } from '~/features/nutrition/format'

const props = defineProps<{ today: string }>()

const PERIODS = [7, 30] as const
const days = ref<(typeof PERIODS)[number]>(7)

const api = useApi()
const { data: averages, status, error, refresh } = useAsyncData(
  () => NUTRITION_KEYS.averages(days.value, props.today),
  () => api.get('/api/nutrition/averages', { query: { days: days.value, today: props.today } }),
  { watch: [days, () => props.today] },
)

const rows = computed(() => {
  const average = averages.value?.dailyAverage
  const target = averages.value?.currentTarget
  if (!average) return []
  return [
    { label: 'Calories', value: formatKcal(average.energyKcal), target: target ? formatKcal(target.energyKcal) : null },
    { label: 'Protéines', value: formatMacro(average.protein), target: target ? formatMacro(target.proteinGrams) : null },
    { label: 'Glucides', value: formatMacro(average.carbohydrate), target: target ? formatMacro(target.carbohydrateGrams) : null },
    { label: 'Lipides', value: formatMacro(average.fat), target: target ? formatMacro(target.fatGrams) : null },
  ]
})
</script>

<template>
  <UiCard title="Moyennes">
    <template #actions>
      <div
        class="flex gap-1 rounded-full bg-surface-raised p-1"
        role="group"
        aria-label="Période"
      >
        <button
          v-for="period in PERIODS"
          :key="period"
          type="button"
          class="min-h-tap cursor-pointer rounded-full bg-transparent px-3 font-semibold text-muted aria-pressed:bg-accent-fill aria-pressed:text-accent-contrast"
          :aria-pressed="days === period"
          @click="days = period"
        >
          {{ period }}&nbsp;jours
        </button>
      </div>
    </template>

    <p
      v-if="status === 'pending' && !averages"
      class="text-muted"
    >
      Chargement…
    </p>
    <UiAlert
      v-else-if="error && !averages"
      tone="error"
    >
      Impossible de calculer les moyennes.
      <UiButton
        variant="ghost"
        @click="refresh()"
      >
        Réessayer
      </UiButton>
    </UiAlert>
    <template v-else-if="averages">
      <p class="text-muted">
        <template v-if="averages.loggedDays">
          Moyenne par jour des {{ averages.loggedDays }}&nbsp;jour{{ averages.loggedDays > 1 ? 's' : '' }} renseigné{{ averages.loggedDays > 1 ? 's' : '' }} sur les {{ averages.days }}&nbsp;derniers (aujourd’hui non compris).
        </template>
        <template v-else>
          Rien de renseigné sur les {{ averages.days }}&nbsp;derniers jours.
        </template>
      </p>
      <dl
        v-if="rows.length"
        class="grid grid-cols-2 gap-3"
      >
        <div
          v-for="row in rows"
          :key="row.label"
        >
          <dt class="text-sm text-muted">
            {{ row.label }}
          </dt>
          <dd class="font-semibold tabular-nums">
            {{ row.value }}<span
              v-if="row.target"
              class="text-muted"
            > / {{ row.target }}</span>
          </dd>
        </div>
      </dl>
    </template>
  </UiCard>
</template>
