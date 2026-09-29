<script setup lang="ts" generic="T extends string">
import { fieldClass } from './classes'

const model = defineModel<T | null>({ default: null })

withDefaults(defineProps<{
  label: string
  options: ReadonlyArray<{ value: T, label: string }>
  placeholder?: string
  error?: string
  /** False for a required choice: no empty option. */
  nullable?: boolean
}>(), {
  placeholder: 'Non renseigné',
  error: undefined,
  nullable: true,
})

const id = useId()
</script>

<template>
  <div :class="fieldClass.root">
    <label
      :for="id"
      :class="fieldClass.label"
    >{{ label }}</label>
    <select
      :id="id"
      v-model="model"
      class="h-tap px-3 text-base focus-visible:border-accent"
      :class="fieldClass.box"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
    >
      <option
        v-if="nullable"
        :value="null"
      >
        {{ placeholder }}
      </option>
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>
    <p
      v-if="error"
      :id="`${id}-error`"
      :class="fieldClass.error"
    >
      {{ error }}
    </p>
  </div>
</template>
