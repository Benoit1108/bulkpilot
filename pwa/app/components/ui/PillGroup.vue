<script setup lang="ts" generic="T extends string">
import { PILL } from './classes'

/**
 * A choice among a few, as pills: radios hidden in their labels, grouped under a legend. The label
 * shown stays in the accessible name; `long` adds what it stands for, for screen readers only.
 */
const value = defineModel<T>({ required: true })
const props = withDefaults(defineProps<{
  legend: string
  name: string
  options: ReadonlyArray<{ value: T, label: string, long?: string }>
  /** The legend is read, not shown (the choice is obvious where it stands). */
  hideLegend?: boolean
}>(), { hideLegend: false })
/** Many options share one row on a phone rather than leaving the last one alone below. */
const crowded = computed(() => props.options.length > 3)
const row = computed(() => (crowded.value ? 'grid auto-cols-fr grid-flow-col gap-1.5 sm:flex sm:flex-wrap sm:gap-2' : 'flex flex-wrap gap-2'))
</script>

<template>
  <fieldset class="m-0 min-w-0 border-0 p-0">
    <legend :class="hideLegend ? 'sr-only' : 'mb-1 text-sm font-semibold'">
      {{ legend }}
    </legend>
    <!-- Many options share one row on a phone (equal columns, tighter pills) rather than leaving the
         last one alone on a second line; from sm they wrap as they come. -->
    <div :class="row">
      <label
        v-for="option in options"
        :key="option.value"
        :class="[PILL, { 'max-sm:px-1 max-sm:has-checked:px-[3px]': crowded }]"
      >
        <input
          v-model="value"
          type="radio"
          :name="name"
          :value="option.value"
          class="sr-only"
        >
        {{ option.label }}<span
          v-if="option.long"
          class="sr-only"
        > ({{ option.long }})</span>
      </label>
    </div>
  </fieldset>
</template>
