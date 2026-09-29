<script setup lang="ts">
import { DASHES, FILLS, STROKES, type ChartSeries } from './chart'

/** What each line of a chart stands for, drawn the way the chart draws it (ADR-0026). */
defineProps<{ series: ReadonlyArray<Pick<ChartSeries, 'name' | 'variant'> & { tone: NonNullable<ChartSeries['tone']> }> }>()
</script>

<template>
  <ul class="m-0 flex list-none flex-wrap gap-x-4 gap-y-1 p-0 text-sm text-muted">
    <li
      v-for="line in series"
      :key="line.name"
      class="inline-flex items-center gap-2"
    >
      <svg
        width="24"
        height="8"
        aria-hidden="true"
      >
        <circle
          v-if="line.variant === 'dots'"
          cx="12"
          cy="4"
          r="3"
          :class="FILLS[line.tone]"
        />
        <line
          v-else
          x1="0"
          x2="24"
          y1="4"
          y2="4"
          stroke-width="2"
          :stroke-dasharray="DASHES[line.variant]"
          :class="STROKES[line.tone]"
        />
      </svg>
      {{ line.name }}
    </li>
  </ul>
</template>
