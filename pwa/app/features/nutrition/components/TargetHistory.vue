<script setup lang="ts">
import { localDay, parseDay } from '~/services/calendar/days'
import MacroLine from '~/features/nutrition/components/MacroLine.vue'
import { formatKcal } from '~/features/nutrition/format'
import { TARGET_SOURCES, targetInForce, type NutritionTarget } from '~/features/nutrition/nutrition'

/**
 * The targets over time, as a timeline, latest first: the ones in force today stand out (a later,
 * planned one may come first), named in words too, not by the colour of their dot alone.
 */
const props = defineProps<{ targets: readonly NutritionTarget[] }>()
const today = localDay()
const dayFormat = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })

const steps = computed(() => {
  const inForce = targetInForce(props.targets, today)?.id
  return props.targets.map((target) => {
    const planned = target.effectiveFrom > today
    const current = target.id === inForce
    return {
      target,
      current,
      state: planned ? 'Prévu' : current ? 'En vigueur' : null,
      since: `${planned ? 'à partir du' : 'depuis le'} ${dayFormat.format(parseDay(target.effectiveFrom))} · ${TARGET_SOURCES[target.source]}`,
      dot: current ? 'bg-accent ring-accent' : planned ? 'bg-surface ring-accent' : 'bg-surface ring-line',
    }
  })
})
</script>

<template>
  <ol
    v-if="targets.length"
    class="relative m-0 flex flex-col gap-4 p-0 before:absolute before:inset-y-2 before:left-1.5 before:w-px before:bg-line"
    role="list"
  >
    <li
      v-for="step in steps"
      :key="step.target.id"
      class="relative flex flex-col gap-0.5 pl-7"
      :aria-current="step.current || undefined"
    >
      <span
        class="absolute top-1.5 left-0 size-3 rounded-full ring-2 ring-inset"
        :class="step.dot"
        aria-hidden="true"
      />
      <p
        class="flex flex-wrap items-baseline gap-x-2 font-semibold tabular-nums"
        :class="{ 'text-accent-ink': step.current }"
      >
        {{ formatKcal(step.target.energyKcal) }}
        <span
          v-if="step.state"
          class="text-footnote font-normal"
        >{{ step.state }}</span>
      </p>
      <p class="text-sm text-muted">
        {{ step.since }}
      </p>
      <p class="text-sm text-muted">
        <MacroLine
          :protein="step.target.proteinGrams"
          :carbohydrate="step.target.carbohydrateGrams"
          :fat="step.target.fatGrams"
        />
      </p>
    </li>
  </ol>
  <p
    v-else
    class="text-sm text-muted"
  >
    Aucun objectif enregistré pour l’instant.
  </p>
</template>
