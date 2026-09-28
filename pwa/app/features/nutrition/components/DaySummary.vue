<script setup lang="ts">
import { formatKcal, formatMacro } from '~/features/nutrition/format'
import { MEAL_BUILDER_MIN_KCAL } from '~/features/nutrition/rules'
import type { NutritionDay } from '~/features/nutrition/nutrition'

const props = defineProps<{ day: NutritionDay }>()
const emit = defineEmits<{ build: [] }>()

const target = computed(() => props.day.target)
const eaten = computed(() => props.day.totals.energyKcal)
const gap = computed(() => (target.value ? Math.round(target.value.energyKcal - eaten.value) : null))

const macros = computed(() => [
  { key: 'protein', label: 'Protéines', value: props.day.totals.protein, target: target.value?.proteinGrams ?? null },
  { key: 'carbohydrate', label: 'Glucides', value: props.day.totals.carbohydrate, target: target.value?.carbohydrateGrams ?? null },
  { key: 'fat', label: 'Lipides', value: props.day.totals.fat, target: target.value?.fatGrams ?? null },
])

function macroText(value: number, max: number | null): string {
  return max === null ? formatMacro(value) : `${formatMacro(value)} sur ${formatMacro(max)}`
}
</script>

<template>
  <UiCard
    title="Bilan du jour"
    class="summary"
  >
    <div class="summary__energy">
      <p class="summary__kcal">
        <span class="summary__eaten">{{ formatKcal(eaten) }}</span>
        <span
          v-if="target"
          class="summary__target"
        > sur {{ formatKcal(target.energyKcal) }}</span>
      </p>
      <p
        v-if="gap !== null"
        class="summary__gap"
        data-testid="energy-gap"
      >
        <template v-if="gap > 0">
          Encore {{ formatKcal(gap) }}
        </template>
        <template v-else-if="gap < 0">
          +{{ formatKcal(-gap) }} au-delà de l’objectif
        </template>
        <template v-else>
          Objectif atteint
        </template>
      </p>
    </div>
    <UiProgressBar
      label="Calories"
      size="lg"
      :value="eaten"
      :max="target?.energyKcal ?? null"
      :value-text="target ? `${formatKcal(eaten)} sur ${formatKcal(target.energyKcal)}` : formatKcal(eaten)"
    />

    <dl class="summary__macros">
      <div
        v-for="macro in macros"
        :key="macro.key"
        class="summary__macro"
      >
        <dt>{{ macro.label }}</dt>
        <dd>{{ macroText(macro.value, macro.target) }}</dd>
        <UiProgressBar
          :label="macro.label"
          :value="macro.value"
          :max="macro.target"
          :value-text="macroText(macro.value, macro.target)"
        />
      </div>
    </dl>

    <UiButton
      v-if="gap !== null && gap >= MEAL_BUILDER_MIN_KCAL"
      variant="secondary"
      @click="emit('build')"
    >
      Que manger&#x202F;? Composer un repas
    </UiButton>

    <p
      v-if="!target"
      class="summary__no-target"
    >
      Pas encore d’objectif pour ce jour.
      <NuxtLink to="/nutrition/objectifs">
        Définir mes objectifs
      </NuxtLink>
    </p>
  </UiCard>
</template>

<style scoped>
.summary__energy {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}

.summary__eaten {
  font-size: var(--text-2xl);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.summary__target,
.summary__gap {
  color: var(--color-text-muted);
}

.summary__macros {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
}

.summary__macro {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.summary__macro dt {
  font-size: var(--text-sm);
  font-weight: 600;
}

.summary__macro dd {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-variant-numeric: tabular-nums;
}

.summary__no-target {
  color: var(--color-text-muted);
}

.summary__no-target a {
  color: var(--color-accent-text);
  font-weight: 600;
}
</style>
