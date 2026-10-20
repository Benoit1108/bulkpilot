<script setup lang="ts">
import { Plus, Star } from 'lucide-vue-next'
import { HEADER_BUTTON } from '~/components/ui/classes'
import { formatGrams, formatKcal } from '~/features/nutrition/format'
import { slotLabel, toSlot, type Entry, type Meal, type MealSlot } from '~/features/nutrition/nutrition'

/**
 * One meal of the day, as a grouped list: its name, what it adds up to against its share of the
 * day (a thin bar), a « + » to add to it, and one line per food (touched, it opens for a change).
 */
const props = defineProps<{ meal: Meal }>()
const emit = defineEmits<{ add: [slot: MealSlot], edit: [entry: Entry], favorite: [slot: MealSlot] }>()

const label = computed(() => slotLabel(props.meal.slot))
/** Stable, so the page can bring focus back here once a drawer closed. */
const titleId = computed(() => `meal-title-${props.meal.slot}`)
const subtotal = computed(() => (props.meal.targetEnergyKcal ? `${formatKcal(props.meal.totals.energyKcal)} sur ${formatKcal(props.meal.targetEnergyKcal)}` : formatKcal(props.meal.totals.energyKcal)))
</script>

<template>
  <section
    class="flex flex-col gap-2"
    :aria-labelledby="titleId"
    :data-testid="`meal-${meal.slot}`"
  >
    <header class="flex items-center gap-3 px-1">
      <h2
        :id="titleId"
        class="min-w-0 flex-1 text-lg font-semibold focus:outline-none"
        tabindex="-1"
      >
        {{ label }}
      </h2>
      <p class="text-sm whitespace-nowrap text-muted tabular-nums">
        {{ subtotal }}
      </p>
      <button
        type="button"
        :class="`${HEADER_BUTTON} shrink-0`"
        :aria-label="`Ajouter un aliment ${toSlot(meal.slot)}`"
        @click="emit('add', meal.slot)"
      >
        <Plus
          :size="22"
          aria-hidden="true"
        />
      </button>
    </header>
    <!-- Its share of the day's target, drawn: the subtotal above already says it. -->
    <div
      v-if="meal.targetEnergyKcal && meal.entries.length"
      class="mx-1"
      aria-hidden="true"
    >
      <UiProgressBar
        :label="label"
        :value="meal.totals.energyKcal"
        :max="meal.targetEnergyKcal"
        :value-text="subtotal"
      />
    </div>

    <ul
      v-if="meal.entries.length"
      class="divide-y divide-line overflow-hidden rounded-lg bg-surface"
      role="list"
    >
      <li
        v-for="entry in meal.entries"
        :key="entry.id"
      >
        <button
          type="button"
          class="flex min-h-tap w-full cursor-pointer items-center gap-3 bg-transparent px-4 py-2 text-left text-inherit hover:bg-surface-raised focus-visible:-outline-offset-2"
          @click="emit('edit', entry)"
        >
          <span class="min-w-0 flex-1 font-semibold wrap-anywhere"><span class="sr-only">Modifier </span>{{ entry.name }}</span>
          <span class="shrink-0 text-sm text-muted tabular-nums">{{ formatGrams(entry.grams) }}</span>
          <span class="w-20 shrink-0 text-right tabular-nums">{{ formatKcal(entry.nutrients.energyKcal) }}</span>
        </button>
      </li>
    </ul>
    <UiButton
      v-if="meal.entries.length"
      variant="ghost"
      class="self-start"
      :aria-label="`Enregistrer « ${label} » comme repas favori`"
      @click="emit('favorite', meal.slot)"
    >
      <Star
        :size="18"
        aria-hidden="true"
      />
      Favori
    </UiButton>
  </section>
</template>
