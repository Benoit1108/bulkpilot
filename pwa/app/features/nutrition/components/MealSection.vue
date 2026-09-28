<script setup lang="ts">
import { Plus, Star } from 'lucide-vue-next'
import { formatGrams, formatKcal } from '~/features/nutrition/format'
import { slotLabel, toSlot, type Entry, type Meal, type MealSlot } from '~/features/nutrition/nutrition'

const props = defineProps<{ meal: Meal }>()
const emit = defineEmits<{ add: [slot: MealSlot], edit: [entry: Entry], favorite: [slot: MealSlot] }>()

const label = computed(() => slotLabel(props.meal.slot))
/** Stable, so the page can bring focus back here once a drawer closed. */
const titleId = computed(() => `meal-title-${props.meal.slot}`)
</script>

<template>
  <section
    class="meal"
    :aria-labelledby="titleId"
    :data-testid="`meal-${meal.slot}`"
  >
    <header class="meal__header">
      <h2
        :id="titleId"
        class="meal__title"
        tabindex="-1"
      >
        {{ label }}
      </h2>
      <p class="meal__energy">
        {{ formatKcal(meal.totals.energyKcal) }}<template v-if="meal.targetEnergyKcal">
          / {{ formatKcal(meal.targetEnergyKcal) }}
        </template>
      </p>
    </header>

    <ul
      v-if="meal.entries.length"
      class="meal__entries"
    >
      <li
        v-for="entry in meal.entries"
        :key="entry.id"
      >
        <button
          type="button"
          class="entry"
          @click="emit('edit', entry)"
        >
          <span class="entry__name"><span class="visually-hidden">Modifier </span>{{ entry.name }}</span>
          <span class="entry__detail">{{ formatGrams(entry.grams) }} · {{ formatKcal(entry.nutrients.energyKcal) }}</span>
        </button>
      </li>
    </ul>

    <div class="meal__actions">
      <UiButton
        variant="ghost"
        :aria-label="`Ajouter un aliment ${toSlot(meal.slot)}`"
        @click="emit('add', meal.slot)"
      >
        <Plus
          :size="18"
          aria-hidden="true"
        />
        Ajouter
      </UiButton>
      <UiButton
        v-if="meal.entries.length"
        variant="ghost"
        :aria-label="`Enregistrer « ${label} » comme repas favori`"
        @click="emit('favorite', meal.slot)"
      >
        <Star
          :size="18"
          aria-hidden="true"
        />
        Favori
      </UiButton>
    </div>
  </section>
</template>

<style scoped>
.meal {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.meal__header {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
}

.meal__title {
  font-size: var(--text-md);
  font-weight: 700;
}

.meal__title:focus {
  outline: none;
}

.meal__energy {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.meal__entries {
  display: flex;
  flex-direction: column;
}

.entry {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
  width: 100%;
  min-height: var(--tap-target);
  padding: var(--space-2) 0;
  background: transparent;
  border: 0;
  border-top: 1px solid var(--color-border);
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.entry:focus-visible {
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

.entry__name {
  font-weight: 600;
}

.entry__detail {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-variant-numeric: tabular-nums;
}

.meal__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
</style>
