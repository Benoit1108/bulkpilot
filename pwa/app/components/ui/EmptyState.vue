<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  icon: Component
  title: string
  description?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
    <span class="mb-2 grid size-14 place-items-center rounded-full bg-accent-soft text-accent-ink">
      <component
        :is="icon"
        :size="28"
        aria-hidden="true"
      />
    </span>
    <p class="font-semibold">
      {{ title }}
    </p>
    <p
      v-if="description"
      class="max-w-[36ch] text-sm text-muted"
    >
      {{ description }}
    </p>
    <slot />
  </div>
</template>
