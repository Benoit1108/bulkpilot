<script setup lang="ts">
import { localDay, parseDay } from '~/services/calendar/days'
import MacroLine from '~/features/nutrition/components/MacroLine.vue'
import { formatKcal } from '~/features/nutrition/format'
import { TARGET_SOURCES, targetInForce, type NutritionTarget } from '~/features/nutrition/nutrition'

const props = defineProps<{ targets: readonly NutritionTarget[] }>()
/** The targets in force today stand out (a later, planned one may come first). */
const inForce = computed(() => targetInForce(props.targets, localDay())?.id)

const dayFormat = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
  <ol
    v-if="targets.length"
    class="m-0 flex list-none flex-col p-0"
  >
    <li
      v-for="target in targets"
      :key="target.id"
      class="border-b border-line py-2"
    >
      <p
        class="font-bold"
        :class="{ 'text-accent-ink': target.id === inForce }"
      >
        {{ formatKcal(target.energyKcal) }}
        <span class="text-sm font-normal text-muted">depuis le {{ dayFormat.format(parseDay(target.effectiveFrom)) }} · {{ TARGET_SOURCES[target.source] }}</span>
      </p>
      <p class="text-sm text-muted">
        <MacroLine
          :protein="target.proteinGrams"
          :carbohydrate="target.carbohydrateGrams"
          :fat="target.fatGrams"
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
