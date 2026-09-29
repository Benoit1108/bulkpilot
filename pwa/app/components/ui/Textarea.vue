<script setup lang="ts">
import { fieldClass } from './classes'

const model = defineModel<string | null>({ default: '' })

withDefaults(defineProps<{
  label: string
  hint?: string
  error?: string
  maxlength?: number
  rows?: number
}>(), { hint: undefined, error: undefined, maxlength: undefined, rows: 3 })

const id = useId()
</script>

<template>
  <div :class="fieldClass.root">
    <label
      :for="id"
      :class="fieldClass.label"
    >{{ label }}</label>
    <!-- 16px minimum (text-base): iOS zooms into smaller inputs. -->
    <textarea
      :id="id"
      v-model="model"
      class="resize-y px-3 py-2 text-base focus:border-accent focus:outline-2 focus:outline-offset-1 focus:outline-accent"
      :class="fieldClass.box"
      :rows="rows"
      :maxlength="maxlength"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="[hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined"
    />
    <p
      v-if="hint"
      :id="`${id}-hint`"
      :class="fieldClass.hint"
    >
      {{ hint }}
    </p>
    <p
      v-if="error"
      :id="`${id}-error`"
      :class="fieldClass.error"
    >
      {{ error }}
    </p>
  </div>
</template>
