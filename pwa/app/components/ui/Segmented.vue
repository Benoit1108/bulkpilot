<script setup lang="ts" generic="T extends string">
/**
 * A choice among a few, as an iOS segmented control: radios hidden in their segments, grouped
 * under a legend. The chosen segment is a raised thumb with an edge (>= 3:1) and a heavier label,
 * so the choice never rests on colour alone. The label shown stays in the accessible name; `long`
 * adds what it stands for, for screen readers only.
 */
const value = defineModel<T>({ required: true })
const props = defineProps<{
  legend: string
  name: string
  options: ReadonlyArray<{ value: T, label: string, long?: string }>
  /** The legend is read, not shown (the choice is obvious where it stands). */
  hideLegend?: boolean
}>()
/** Past four segments a phone has no room for one row of 44px targets: they go three by row. */
const crowded = computed(() => props.options.length > 4)
</script>

<template>
  <fieldset class="m-0 min-w-0 border-0 p-0">
    <legend :class="hideLegend ? 'sr-only' : 'mb-1 text-sm font-semibold'">
      {{ legend }}
    </legend>
    <!-- Equal segments, as wide as the longest label; a phone gives them the whole width. -->
    <div
      class="grid w-full auto-cols-fr grid-flow-col gap-0.5 rounded-lg bg-track p-0.5 sm:inline-grid sm:w-auto"
      :class="{ 'max-sm:grid-flow-row max-sm:grid-cols-3': crowded }"
    >
      <label
        v-for="option in options"
        :key="option.value"
        class="relative flex min-h-tap min-w-tap cursor-pointer items-center justify-center rounded-md px-1 text-center text-footnote whitespace-nowrap text-ink transition-colors hover:bg-thumb/40 has-checked:bg-thumb has-checked:font-semibold has-checked:ring-1 has-checked:ring-thumb-edge has-focus-visible:outline-2 has-focus-visible:outline-offset-1 has-focus-visible:outline-accent sm:px-4 sm:text-sm"
      >
        <!-- Over the whole segment, transparent: the radio is what is touched (and what tests check). -->
        <input
          v-model="value"
          type="radio"
          :name="name"
          :value="option.value"
          class="absolute inset-0 m-0 cursor-pointer opacity-0"
        >
        {{ option.label }}<span
          v-if="option.long"
          class="sr-only"
        > ({{ option.long }})</span>
      </label>
    </div>
  </fieldset>
</template>
