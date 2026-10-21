<script setup lang="ts">
import { LEVELS, type Recommendation } from '../coach'

/** « Prioritaire », « À surveiller », « Information »: the word, after an icon in its tone (decorative). */
const props = defineProps<{ level: Recommendation['level'] }>()
const shown = computed(() => LEVELS[props.level])
</script>

<template>
  <span class="inline-flex items-center gap-1">
    <component
      :is="shown.icon"
      class="size-4 shrink-0"
      :class="shown.tint"
      aria-hidden="true"
    />
    {{ shown.label }}
  </span>
</template>
