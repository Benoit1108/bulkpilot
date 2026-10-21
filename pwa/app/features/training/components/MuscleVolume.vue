<script setup lang="ts">
import type { components } from '~/types/api'
import { counted } from '~/services/text/plural'
import { labelOf, MUSCLES } from '../training'

/**
 * The working sets of the week under way, by the main muscle of their exercise, the most worked
 * first: a bar each, as long as its share of the most worked one. The figures are in the text, the
 * bars only show the balance (hidden from screen readers).
 */
const props = defineProps<{ muscles: components['schemas']['TrainingWeek']['muscles'] }>()

const rows = computed(() => {
  const top = Math.max(1, ...props.muscles.map(muscle => muscle.workingSets))
  return props.muscles.map(muscle => ({
    key: muscle.muscle,
    label: labelOf(MUSCLES, muscle.muscle),
    sets: counted(muscle.workingSets, 'série'),
    width: `${Math.round((muscle.workingSets / top) * 100)}%`,
  }))
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <ul
      v-if="rows.length"
      class="m-0 flex flex-col gap-2 p-0"
      role="list"
    >
      <li
        v-for="row in rows"
        :key="row.key"
        class="grid grid-cols-[minmax(0,8rem)_1fr_auto] items-center gap-3"
      >
        <span class="text-sm wrap-anywhere">{{ row.label }}</span>
        <span
          class="h-2 overflow-hidden rounded-full bg-surface-raised"
          aria-hidden="true"
        >
          <span
            class="block h-full rounded-full bg-accent"
            :style="{ width: row.width }"
          />
        </span>
        <span class="text-sm font-semibold tabular-nums">{{ row.sets }}</span>
      </li>
    </ul>
    <p
      v-else
      class="text-sm text-muted"
    >
      Aucune série de travail cette semaine pour l’instant.
    </p>
    <p
      v-if="rows.length"
      class="text-xs text-muted"
    >
      Séries de travail de la semaine en cours, comptées pour le muscle principal de chaque exercice.
    </p>
  </div>
</template>
