<script setup lang="ts">
import { useServiceHealth } from '~/features/system/health'

const { serviceStatus, apiError, refresh } = useServiceHealth()
const visible = computed(() => serviceStatus.value === 'unreachable' || serviceStatus.value === 'degraded')

useEventListener('online', () => refresh())
</script>

<template>
  <!-- Always mounted so screen readers announce content changes. -->
  <div
    class="connectivity"
    :class="{ 'connectivity--visible': visible }"
    role="status"
  >
    <template v-if="visible">
      <p>{{ apiError?.userMessage ?? 'Le service rencontre un problème.' }}</p>
      <UiButton
        variant="secondary"
        @click="refresh()"
      >
        Réessayer
      </UiButton>
    </template>
  </div>
</template>

<style scoped>
.connectivity--visible {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-danger);
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
}
</style>
