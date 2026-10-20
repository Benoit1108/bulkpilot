<script setup lang="ts">
import { LIST_ROW } from '~/components/ui/classes'
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
    class="flex flex-col gap-2 rounded-lg bg-surface p-4"
    :aria-labelledby="titleId"
    :data-testid="`meal-${meal.slot}`"
  >
    <header class="flex items-baseline justify-between gap-3">
      <h2
        :id="titleId"
        class="text-base font-bold focus:outline-none"
        tabindex="-1"
      >
        {{ label }}
      </h2>
      <p class="text-sm whitespace-nowrap text-muted tabular-nums">
        {{ formatKcal(meal.totals.energyKcal) }}<template v-if="meal.targetEnergyKcal">
          / {{ formatKcal(meal.targetEnergyKcal) }}
        </template>
      </p>
    </header>

    <ul
      v-if="meal.entries.length"
      class="m-0 flex list-none flex-col p-0"
    >
      <li
        v-for="entry in meal.entries"
        :key="entry.id"
      >
        <button
          type="button"
          class="focus-visible:rounded-sm focus-visible:outline-offset-2"
          :class="LIST_ROW"
          @click="emit('edit', entry)"
        >
          <span class="font-semibold"><span class="sr-only">Modifier </span>{{ entry.name }}</span>
          <span class="text-sm text-muted tabular-nums">{{ formatGrams(entry.grams) }} · {{ formatKcal(entry.nutrients.energyKcal) }}</span>
        </button>
      </li>
    </ul>

    <div class="flex flex-wrap gap-2">
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
