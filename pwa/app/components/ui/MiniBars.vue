<script setup lang="ts">
/**
 * Small bars, one per period (a week's sessions), under a tile's figure: the tile says the figure
 * in words, the bars only show the rhythm (hidden from screen readers). An empty period is a stub;
 * under three periods there is no rhythm to show.
 */
const props = defineProps<{ values: readonly number[] }>()
const bars = computed(() => {
  const top = Math.max(1, ...props.values)
  return props.values.map(value => ({ height: `${Math.round((value / top) * 100)}%`, fill: value === 0 ? 'bg-line' : 'bg-accent' }))
})
</script>

<template>
  <div
    v-if="values.length >= 3"
    class="flex h-8 items-end gap-1"
    aria-hidden="true"
  >
    <span
      v-for="(bar, i) in bars"
      :key="i"
      class="min-h-1 flex-1 rounded-sm"
      :class="bar.fill"
      :style="{ height: bar.height }"
    />
  </div>
</template>
