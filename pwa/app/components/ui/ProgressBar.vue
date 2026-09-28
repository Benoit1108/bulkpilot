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
    class="ui-progress"
    :class="[`ui-progress--${size}`, { 'ui-progress--over': over, 'ui-progress--empty': !max }]"
    :role="max ? 'progressbar' : undefined"
    :aria-label="max ? label : undefined"
    :aria-valuemin="max ? 0 : undefined"
    :aria-valuemax="max ?? undefined"
    :aria-valuenow="max ? Math.min(max, Math.round(value)) : undefined"
    :aria-valuetext="max ? valueText : undefined"
    :aria-hidden="max ? undefined : 'true'"
  >
    <span
      class="ui-progress__fill"
      :style="{ width: fill }"
    />
  </div>
</template>

<style scoped>
.ui-progress {
  position: relative;
  overflow: hidden;
  height: 0.5rem;
  background: var(--color-surface-raised);
  border-radius: var(--radius-full);
}

.ui-progress--lg {
  height: 0.75rem;
}

.ui-progress__fill {
  position: absolute;
  inset: 0 auto 0 0;
  background: var(--color-accent);
  border-radius: inherit;
  transition: width var(--transition-fast);
}

.ui-progress--over .ui-progress__fill {
  /* Over the target is not an error (spec §30): neutral, yet distinct from "on the way". */
  background: var(--color-text-muted);
}

@media (prefers-reduced-motion: reduce) {
  .ui-progress__fill {
    transition: none;
  }
}
</style>
