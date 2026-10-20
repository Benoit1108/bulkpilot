<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import { QUICK_ADD } from '~/components/ui/classes'
import { formatKcal } from '~/features/nutrition/format'
import type { FavoriteMeal } from '~/features/nutrition/nutrition'

/** The favourite meals of the add panel: a whole meal is added in one tap. */
defineProps<{ meals: readonly FavoriteMeal[] }>()
const emit = defineEmits<{ pick: [meal: FavoriteMeal] }>()

const detail = (meal: FavoriteMeal) => `${meal.items.map(item => item.name).join(', ')} · ${formatKcal(meal.totals.energyKcal)}`
</script>

<template>
  <section
    class="flex flex-col"
    aria-label="Repas favoris"
  >
    <ul
      class="flex flex-col divide-y divide-line"
      role="list"
    >
      <li
        v-for="meal in meals"
        :key="meal.id"
      >
        <!-- A favourite is a whole meal: the whole row adds it, its « + » included (no quantity to set). -->
        <button
          type="button"
          class="flex min-h-tap w-full cursor-pointer items-center gap-2 border-0 bg-transparent px-0 py-2 text-left text-inherit"
          @click="emit('pick', meal)"
        >
          <span class="flex min-w-0 flex-1 flex-col items-start gap-1">
            <span class="font-semibold wrap-anywhere"><span class="sr-only">Ajouter le repas favori </span>{{ meal.name }}</span>
            <span class="text-sm text-muted">{{ detail(meal) }}</span>
          </span>
          <span
            :class="QUICK_ADD"
            aria-hidden="true"
          >
            <Plus :size="20" />
          </span>
        </button>
      </li>
    </ul>
  </section>
</template>
