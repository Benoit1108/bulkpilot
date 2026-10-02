<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import { ChevronLeft } from 'lucide-vue-next'

defineProps<{ to: string, label: string }>()
</script>

<template>
  <NuxtLink
    :to="to"
    :class="[TEXT_LINK, 'gap-1 no-underline']"
  >
    <ChevronLeft
      :size="18"
      aria-hidden="true"
    />
    {{ label }}
  </NuxtLink>
</template>
