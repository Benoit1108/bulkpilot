<script setup lang="ts">
import { useServiceHealth } from '~/features/system/health'

const { serviceStatus, apiError, refresh } = useServiceHealth()
const visible = computed(() => serviceStatus.value === 'unreachable' || serviceStatus.value === 'degraded')

useEventListener('online', () => refresh())
</script>

<template>
  <!-- Always mounted so screen readers announce content changes. -->
  <div
    :class="{ 'flex items-center justify-between gap-3 rounded-md border border-danger bg-surface-raised px-4 py-3 text-sm': visible }"
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
