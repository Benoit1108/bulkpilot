<script setup lang="ts">
import { parseDay } from '~/features/nutrition/days'
import MacroLine from '~/features/nutrition/components/MacroLine.vue'
import { formatKcal } from '~/features/nutrition/format'
import { TARGET_SOURCES, type NutritionTarget } from '~/features/nutrition/nutrition'

defineProps<{ targets: readonly NutritionTarget[] }>()

const dayFormat = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
</script>

<template>
  <ol
    v-if="targets.length"
    class="history"
  >
    <li
      v-for="target in targets"
      :key="target.id"
      class="history__item"
    >
      <p class="history__title">
        {{ formatKcal(target.energyKcal) }}
        <span class="history__meta">depuis le {{ dayFormat.format(parseDay(target.effectiveFrom)) }} · {{ TARGET_SOURCES[target.source] }}</span>
      </p>
      <p class="history__macros">
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
    class="history__empty"
  >
    Aucun objectif enregistré pour l’instant.
  </p>
</template>

<style scoped>
.history {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.history__item {
  padding: var(--space-2) 0;
  border-bottom: 1px solid var(--color-border);
}

.history__item:first-child .history__title {
  color: var(--color-accent-text);
}

.history__title {
  font-weight: 700;
}

.history__meta,
.history__macros,
.history__empty {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-weight: 400;
}
</style>
