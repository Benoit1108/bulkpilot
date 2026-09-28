<script setup lang="ts">
const props = withDefaults(defineProps<{ tone?: 'error' | 'success' | 'info' }>(), { tone: 'info' })

// A new error takes the focus: keyboard and screen-reader users land on what went wrong,
// instead of on <body> after the submit button re-rendered.
const root = ref<HTMLElement | null>(null)
onMounted(() => {
  if (props.tone === 'error') root.value?.focus()
})
</script>

<template>
  <!-- Errors interrupt (alert); confirmations are announced politely (status). -->
  <div
    ref="root"
    class="ui-alert"
    :class="`ui-alert--${tone}`"
    :role="tone === 'error' ? 'alert' : 'status'"
    :tabindex="tone === 'error' ? -1 : undefined"
  >
    <slot />
  </div>
</template>

<style scoped>
.ui-alert {
  padding: var(--space-3) var(--space-4);
  border: 1px solid var(--color-border);
  border-left-width: 4px;
  border-radius: var(--radius-md);
  background: var(--color-surface-raised);
  font-size: var(--text-sm);
}

.ui-alert:focus {
  outline: none;
}

.ui-alert:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.ui-alert--error {
  border-left-color: var(--color-danger);
}

.ui-alert--success {
  border-left-color: var(--color-success);
}

.ui-alert--info {
  border-left-color: var(--color-accent);
}

.ui-alert :deep(a) {
  color: var(--color-accent-text);
  font-weight: 600;
}
</style>
