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
        class="periods"
        role="group"
        aria-label="Période"
      >
        <button
          v-for="period in PERIODS"
          :key="period"
          type="button"
          class="periods__option"
          :aria-pressed="days === period"
          @click="days = period"
        >
          {{ period }}&nbsp;jours
        </button>
      </div>
    </template>

    <p
      v-if="status === 'pending' && !averages"
      class="muted"
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
      <p class="muted">
        <template v-if="averages.loggedDays">
          Moyenne par jour des {{ averages.loggedDays }}&nbsp;jour{{ averages.loggedDays > 1 ? 's' : '' }} renseigné{{ averages.loggedDays > 1 ? 's' : '' }} sur les {{ averages.days }}&nbsp;derniers (aujourd’hui non compris).
        </template>
        <template v-else>
          Rien de renseigné sur les {{ averages.days }}&nbsp;derniers jours.
        </template>
      </p>
      <dl
        v-if="rows.length"
        class="averages"
      >
        <div
          v-for="row in rows"
          :key="row.label"
          class="averages__row"
        >
          <dt>{{ row.label }}</dt>
          <dd>
            {{ row.value }}<span
              v-if="row.target"
              class="muted"
            > / {{ row.target }}</span>
          </dd>
        </div>
      </dl>
    </template>
  </UiCard>
</template>

<style scoped>
.periods {
  display: flex;
  gap: var(--space-1);
  padding: var(--space-1);
  background: var(--color-surface-raised);
  border-radius: var(--radius-full);
}

.periods__option {
  min-height: var(--tap-target);
  padding: 0 var(--space-3);
  background: transparent;
  border: 0;
  border-radius: var(--radius-full);
  color: var(--color-text-muted);
  font-weight: 600;
  cursor: pointer;
}

.periods__option[aria-pressed='true'] {
  background: var(--color-accent-fill);
  color: var(--color-accent-contrast);
}

.muted {
  color: var(--color-text-muted);
}

.averages {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.averages__row dt {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.averages__row dd {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
</style>
