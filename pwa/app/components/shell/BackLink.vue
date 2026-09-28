<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next'

defineProps<{ to: string, label: string }>()
</script>

<template>
  <NuxtLink
    :to="to"
    class="back-link"
  >
    <ChevronLeft
      :size="18"
      aria-hidden="true"
    />
    {{ label }}
  </NuxtLink>
</template>

<style scoped>
.back-link {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  min-height: var(--tap-target);
  color: var(--color-accent-text);
  font-weight: 600;
  text-decoration: none;
}
</style>
