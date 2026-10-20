<script setup lang="ts">
import { formatMacro } from '~/features/nutrition/format'
import { formatNumber } from '~/services/text/numbers'

/**
 * The three macros side by side, each in its own colour (docs/DESIGN.md): what was eaten against
 * the target, as a bar and in figures. Without a target, the figure alone. The journal's day summary
 * moves to it when the journal is redone (R4).
 */
const props = defineProps<{
  protein: number
  carbohydrate: number
  fat: number
  target: { proteinGrams: number, carbohydrateGrams: number, fatGrams: number } | null
}>()

const macros = computed(() => [
  { label: 'Protéines', tone: 'protein', eaten: props.protein, target: props.target?.proteinGrams ?? null },
  { label: 'Glucides', tone: 'carbs', eaten: props.carbohydrate, target: props.target?.carbohydrateGrams ?? null },
  { label: 'Lipides', tone: 'fat', eaten: props.fat, target: props.target?.fatGrams ?? null },
] as const)
/** « 98 / 150 g »: short enough for a third of a phone; the bar reads it out « 98 g sur 150 g ». */
const shown = (eaten: number, target: number) => `${formatNumber(eaten, 0)} / ${formatMacro(target)}`
const spoken = (eaten: number, target: number) => `${formatMacro(eaten)} sur ${formatMacro(target)}`
</script>

<template>
  <dl class="grid grid-cols-3 gap-3">
    <div
      v-for="macro in macros"
      :key="macro.label"
      class="flex min-w-0 flex-col gap-1"
    >
      <dt class="text-xs text-muted">
        {{ macro.label }}
      </dt>
      <!-- The bar is part of the value (a definition list holds only terms and values); it says the value in full. -->
      <dd class="flex flex-col gap-1 text-sm tabular-nums">
        <template v-if="macro.target !== null">
          <UiProgressBar
            :label="macro.label"
            :value="macro.eaten"
            :max="macro.target"
            :tone="macro.tone"
            :value-text="spoken(macro.eaten, macro.target)"
          />
          <span aria-hidden="true">{{ shown(macro.eaten, macro.target) }}</span>
        </template>
        <span v-else>{{ formatMacro(macro.eaten) }}</span>
      </dd>
    </div>
  </dl>
</template>
