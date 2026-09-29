<script setup lang="ts">
import { buttonClass, type ButtonVariant } from './classes'

const props = withDefaults(defineProps<{
  variant?: ButtonVariant
  type?: 'button' | 'submit' | 'reset'
  loading?: boolean
  disabled?: boolean
}>(), {
  variant: 'primary',
  type: 'button',
  loading: false,
  disabled: false,
})

// While loading the button stays focusable (aria-disabled), so focus does not fall back to <body>.
function guard(event: MouseEvent): void {
  if (props.loading) {
    event.preventDefault()
    event.stopImmediatePropagation()
  }
}
</script>

<template>
  <button
    :type="type"
    :class="buttonClass(variant)"
    :disabled="disabled"
    :aria-disabled="loading || undefined"
    :aria-busy="loading || undefined"
    @click.capture="guard"
  >
    <span
      v-if="loading"
      class="size-[1em] animate-spin rounded-full border-2 border-current border-r-transparent motion-reduce:animate-none"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>
