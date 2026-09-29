<script setup lang="ts">
import { ChevronLeft } from 'lucide-vue-next'

defineProps<{ to: string, label: string }>()
</script>

<template>
  <NuxtLink
    :to="to"
    class="inline-flex min-h-tap items-center gap-1 font-semibold text-accent-ink no-underline"
  >
    <ChevronLeft
      :size="18"
      aria-hidden="true"
    />
    {{ label }}
  </NuxtLink>
</template>
