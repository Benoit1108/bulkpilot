<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  icon: Component
  title: string
  description?: string
}>()
</script>

<template>
  <div class="ui-empty-state">
    <span class="ui-empty-state__icon">
      <component
        :is="icon"
        :size="28"
        aria-hidden="true"
      />
    </span>
    <p class="ui-empty-state__title">
      {{ title }}
    </p>
    <p
      v-if="description"
      class="ui-empty-state__description"
    >
      {{ description }}
    </p>
    <slot />
  </div>
</template>

<style scoped>
.ui-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-6) var(--space-4);
  text-align: center;
}

.ui-empty-state__icon {
  display: grid;
  place-items: center;
  width: 3.5rem;
  height: 3.5rem;
  margin-bottom: var(--space-2);
  border-radius: var(--radius-full);
  background: var(--color-accent-soft);
  color: var(--color-accent-text);
}

.ui-empty-state__title {
  font-weight: 600;
}

.ui-empty-state__description {
  max-width: 36ch;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
