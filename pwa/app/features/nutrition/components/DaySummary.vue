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
  <UiCard title="Bilan du jour">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
      <p>
        <span class="text-2xl font-bold tabular-nums">{{ formatKcal(eaten) }}</span>
        <span
          v-if="target"
          class="text-muted"
        > sur {{ formatKcal(target.energyKcal) }}</span>
      </p>
      <p
        v-if="gap !== null"
        class="text-muted"
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

    <dl class="grid grid-cols-3 gap-3">
      <div
        v-for="macro in macros"
        :key="macro.key"
        class="flex min-w-0 flex-col gap-1"
      >
        <dt class="text-sm font-semibold">
          {{ macro.label }}
        </dt>
        <dd class="text-sm text-muted tabular-nums">
          {{ macroText(macro.value, macro.target) }}
        </dd>
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
      class="text-muted"
    >
      Pas encore d’objectif pour ce jour.
      <NuxtLink
        to="/nutrition/objectifs"
        class="font-semibold text-accent-ink"
      >
        Définir mes objectifs
      </NuxtLink>
    </p>
  </UiCard>
</template>
