<script setup lang="ts" generic="T extends string">
import { PILL } from './classes'

/**
 * A choice among a few, as pills: radios hidden in their labels, grouped under a legend. The label
 * shown stays in the accessible name; `long` adds what it stands for, for screen readers only.
 */
const value = defineModel<T>({ required: true })
withDefaults(defineProps<{
  legend: string
  name: string
  options: ReadonlyArray<{ value: T, label: string, long?: string }>
  /** The legend is read, not shown (the choice is obvious where it stands). */
  hideLegend?: boolean
}>(), { hideLegend: false })
</script>

<template>
  <fieldset class="m-0 min-w-0 border-0 p-0">
    <legend :class="hideLegend ? 'sr-only' : 'mb-1 text-sm font-semibold'">
      {{ legend }}
    </legend>
    <div class="flex flex-wrap gap-2">
      <label
        v-for="option in options"
        :key="option.value"
        :class="PILL"
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
