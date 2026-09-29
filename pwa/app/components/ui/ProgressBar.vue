<script setup lang="ts">
/**
 * Progress towards a target. Going over it is not an error (a bulk often needs it): the bar
 * stays full, shows the overflow in a neutral tone, and the text says by how much.
 */
const props = withDefaults(defineProps<{
  label: string
  value: number
  max: number | null
  /** Screen-reader text, e.g. "1 250 kcal sur 2 800". */
  valueText: string
  size?: 'md' | 'lg'
}>(), { size: 'md' })

const ratio = computed(() => (props.max && props.max > 0 ? props.value / props.max : 0))
const fill = computed(() => `${Math.min(100, Math.round(ratio.value * 100))}%`)
const over = computed(() => ratio.value > 1)
</script>

<template>
  <!-- Without a target there is no progress to report: a plain bar, the figure is in the text next to it. -->
  <div
    class="relative overflow-hidden rounded-full bg-surface-raised"
    :class="size === 'lg' ? 'h-3' : 'h-2'"
    :role="max ? 'progressbar' : undefined"
    :aria-label="max ? label : undefined"
    :aria-valuemin="max ? 0 : undefined"
    :aria-valuemax="max ?? undefined"
    :aria-valuenow="max ? Math.min(max, Math.round(value)) : undefined"
    :aria-valuetext="max ? valueText : undefined"
    :aria-hidden="max ? undefined : 'true'"
  >
    <!-- Over the target is not an error (spec §30): neutral, yet distinct from "on the way". -->
    <span
      class="absolute inset-y-0 left-0 rounded-[inherit] transition-[width] motion-reduce:transition-none"
      :class="over ? 'bg-muted' : 'bg-accent'"
      :style="{ width: fill }"
    />
  </div>
</template>
