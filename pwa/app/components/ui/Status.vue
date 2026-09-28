<script setup lang="ts">
/**
 * A short confirmation ("Poulet ajouté au déjeuner"), visible and announced by screen readers.
 * The region stays mounted (empty) so that assistive technologies notice each new message.
 */
const props = withDefaults(defineProps<{ message: string | null, duration?: number }>(), { duration: 5000 })
const emit = defineEmits<{ dismiss: [] }>()

let timer: ReturnType<typeof setTimeout> | undefined
watch(() => props.message, (message) => {
  clearTimeout(timer)
  if (message) timer = setTimeout(() => emit('dismiss'), props.duration)
})
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div
    class="ui-status"
    role="status"
  >
    <p
      v-if="message"
      class="ui-status__message"
    >
      {{ message }}
    </p>
  </div>
</template>

<style scoped>
.ui-status {
  position: fixed;
  right: var(--space-4);
  bottom: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom) + var(--space-3));
  left: var(--space-4);
  z-index: 30;
  display: flex;
  justify-content: center;
  pointer-events: none;
}

.ui-status__message {
  max-width: var(--reading-max-width);
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-raised);
  font-weight: 600;
}

@media (min-width: 48rem) {
  .ui-status {
    bottom: var(--space-5);
  }
}
</style>
