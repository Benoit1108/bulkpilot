<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost'
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
    class="ui-button"
    :class="`ui-button--${variant}`"
    :disabled="disabled"
    :aria-disabled="loading || undefined"
    :aria-busy="loading || undefined"
    @click.capture="guard"
  >
    <span
      v-if="loading"
      class="ui-button__spinner"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>

<style scoped>
.ui-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  min-height: var(--tap-target);
  padding: 0 var(--space-4);
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  font-weight: 600;
  cursor: pointer;
  transition: background-color var(--transition-fast), border-color var(--transition-fast), opacity var(--transition-fast);
  touch-action: manipulation;
}

.ui-button:disabled,
.ui-button[aria-disabled='true'] {
  cursor: not-allowed;
  opacity: 0.55;
}

.ui-button--primary {
  background: var(--color-accent-fill);
  color: var(--color-accent-contrast);
}

.ui-button--primary:not(:disabled, [aria-disabled='true']):hover {
  background: var(--color-accent-fill-hover);
}

.ui-button--secondary {
  background: var(--color-surface-raised);
  border-color: var(--color-border);
  color: var(--color-text);
}

.ui-button--secondary:not(:disabled, [aria-disabled='true']):hover {
  border-color: var(--color-accent);
}

.ui-button--ghost {
  background: transparent;
  color: var(--color-accent-text);
}

.ui-button--ghost:not(:disabled, [aria-disabled='true']):hover {
  background: var(--color-accent-soft);
}

.ui-button__spinner {
  width: 1em;
  height: 1em;
  border: 2px solid currentcolor;
  border-right-color: transparent;
  border-radius: var(--radius-full);
  animation: ui-button-spin 700ms linear infinite;
}

@keyframes ui-button-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
