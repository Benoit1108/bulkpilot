<script setup lang="ts">
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal, formatMacro } from '~/features/nutrition/format'

const props = defineProps<{ today: string }>()

const PERIODS = [{ value: '7', label: '7\u00A0jours' }, { value: '30', label: '30\u00A0jours' }] as const
const period = ref<(typeof PERIODS)[number]['value']>('7')
const days = computed(() => Number(period.value))

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
      <UiSegmented
        v-model="period"
        legend="Période"
        name="averages-period"
        :options="PERIODS"
        hide-legend
      />
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
