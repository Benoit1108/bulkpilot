<script setup lang="ts">
/**
 * A small line of how a figure moved, under a tile's figure: the tile says the figure and its
 * change in words, the line only shows the shape (hidden from screen readers).
 */
const props = defineProps<{ values: readonly number[] }>()

const points = computed(() => {
  const low = Math.min(...props.values)
  const span = Math.max(...props.values) - low || 1
  const step = 100 / Math.max(1, props.values.length - 1)
  // A little room above and below so the stroke is not cut at the edges.
  return props.values.map((value, i) => `${(i * step).toFixed(1)},${(27 - ((value - low) / span) * 24).toFixed(1)}`).join(' ')
})
</script>

<template>
  <svg
    v-if="values.length > 1"
    viewBox="0 0 100 30"
    preserveAspectRatio="none"
    class="h-8 w-full"
    aria-hidden="true"
  >
    <polyline
      :points="points"
      fill="none"
      stroke-width="2"
      stroke-linecap="round"
      stroke-linejoin="round"
      vector-effect="non-scaling-stroke"
      class="stroke-accent"
    />
  </svg>
</template>
