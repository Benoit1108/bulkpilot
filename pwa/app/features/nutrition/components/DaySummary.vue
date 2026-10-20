<script setup lang="ts">
import MacroBars from '~/features/nutrition/components/MacroBars.vue'
import { formatKcal } from '~/features/nutrition/format'
import { MEAL_BUILDER_MIN_KCAL } from '~/features/nutrition/rules'
import type { NutritionDay } from '~/features/nutrition/nutrition'

/**
 * The day at a glance, as the dashboard reads it: the calories left (or past the target, never as an
 * alarm), the ring of the day, each macro in its colour, and « Que manger ? » while there is room.
 */
const props = defineProps<{ day: NutritionDay }>()
const emit = defineEmits<{ build: [] }>()

const target = computed(() => props.day.target)
const eaten = computed(() => props.day.totals.energyKcal)
const gap = computed(() => (target.value ? Math.round(target.value.energyKcal - eaten.value) : null))
/** What is left, or how far past the target (never an alarm); the target met exactly says so in full. */
const left = computed(() => {
  if (gap.value === null) return null
  if (gap.value > 0) return { label: 'Encore', figure: formatKcal(gap.value) }
  if (gap.value < 0) return { label: 'Au-delà de l’objectif', figure: `+${formatKcal(-gap.value)}` }
  return { label: null, figure: 'Objectif atteint' }
})
</script>

<template>
  <UiCard title="Bilan du jour">
    <div class="flex flex-wrap items-center gap-4">
      <!-- The figures are in the text beside it: the ring only draws them. -->
      <UiRing
        v-if="target"
        :rings="[{ value: eaten, max: target.energyKcal, tone: 'accent' }]"
        class="size-20 sm:size-24"
      />
      <div class="flex min-w-0 flex-col">
        <p
          v-if="left"
          class="text-2xl font-bold tabular-nums sm:text-figure"
          data-testid="energy-gap"
        >
          <span
            v-if="left.label"
            class="block text-sm font-normal text-muted"
          >{{ left.label }}</span> {{ left.figure }}
        </p>
        <p
          class="text-muted tabular-nums"
          :class="{ 'text-2xl font-bold text-ink': !target }"
        >
          {{ formatKcal(eaten) }}<template v-if="target">
            sur {{ formatKcal(target.energyKcal) }}
          </template>
        </p>
      </div>
    </div>
    <MacroBars
      :protein="day.totals.protein"
      :carbohydrate="day.totals.carbohydrate"
      :fat="day.totals.fat"
      :target="target"
    />

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
