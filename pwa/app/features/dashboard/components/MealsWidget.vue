<script setup lang="ts">
import type { DashboardView } from '../dashboard'
import { formatKcal } from '~/features/nutrition/format'
import { slotLabel } from '~/features/nutrition/nutrition'
import { SHOWN_WIDGETS } from '../data'
import DashboardWidget from './DashboardWidget.vue'

/** The day's meals (spec §13 « repas »), each with what it adds up to. */
// Every widget is given the view; this one is the same whatever it is.
const props = defineProps<{ today: string, view?: DashboardView }>()

// The journal's own day (a stale day is not shown past midnight).
const { journal: day, status, error, refresh } = useNutritionDay(toRef(props, 'today'))
const meals = computed(() => (day.value?.meals ?? []).filter(meal => meal.entries.length > 0))
// With the Nutrition widget shown, an empty day is said (and offered to fill) once, there.
const shownWidgets = inject(SHOWN_WIDGETS, ref([]))
const alone = computed(() => !shownWidgets.value.includes('nutrition'))
</script>

<template>
  <DashboardWidget
    title="Repas"
    to="/nutrition"
    :loading="status === 'pending' && !day"
    :failed="Boolean(error) && !day"
    @retry="refresh()"
  >
    <ul
      v-if="meals.length"
      class="m-0 flex list-none flex-col p-0"
      role="list"
    >
      <li
        v-for="meal in meals"
        :key="meal.slot"
        class="flex items-baseline justify-between gap-3 border-t border-line py-2 first:border-t-0"
      >
        <span>
          <span class="font-semibold">{{ slotLabel(meal.slot) }}</span>
          <span class="block text-sm text-muted">{{ meal.entries.map(entry => entry.name).join(', ') }}</span>
        </span>
        <span class="shrink-0 tabular-nums">{{ formatKcal(meal.totals.energyKcal) }}</span>
      </li>
    </ul>
    <div
      v-else
      class="flex flex-col gap-2"
    >
      <p class="text-sm text-muted">
        Aucun repas saisi aujourd’hui.
      </p>
      <UiLinkButton
        v-if="alone"
        :to="{ path: '/nutrition', query: { ajouter: 'aliment' } }"
        variant="secondary"
      >
        Ajouter un aliment
      </UiLinkButton>
    </div>
  </DashboardWidget>
</template>
