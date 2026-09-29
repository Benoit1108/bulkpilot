<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next'

/** A figure typed in, or moved by steps with buttons big enough for a thumb between two sets. */
const model = defineModel<number | string | null>({ default: '' })

withDefaults(defineProps<{
  label: string
  lessLabel: string
  moreLabel: string
  suffix?: string
  hint?: string
  error?: string
  inputmode?: 'numeric' | 'decimal'
}>(), { suffix: undefined, hint: undefined, error: undefined, inputmode: undefined })

const emit = defineEmits<{ less: [], more: [] }>()

const field = ref<{ focus: () => void } | null>(null)
defineExpose({ focus: () => field.value?.focus() })
</script>

<template>
  <!-- The figure is what the eye looks for: large, bold, digits of equal width. -->
  <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2 [&_input]:text-number [&_input]:font-bold [&_input]:tabular-nums">
    <UiInput
      ref="field"
      v-model="model"
      :label="label"
      type="number"
      :inputmode="inputmode"
      :suffix="suffix"
      :hint="hint"
      :error="error"
    />
    <!-- Level with the field, below its label. -->
    <div class="mt-label flex gap-2">
      <UiButton
        variant="secondary"
        class="min-w-13"
        :aria-label="lessLabel"
        @click="emit('less')"
      >
        <Minus
          :size="20"
          aria-hidden="true"
        />
      </UiButton>
      <UiButton
        variant="secondary"
        class="min-w-13"
        :aria-label="moreLabel"
        @click="emit('more')"
      >
        <Plus
          :size="20"
          aria-hidden="true"
        />
      </UiButton>
    </div>
  </div>
</template>
