<script setup lang="ts">
/**
 * A page's content. « reading » (the default): one column at a comfortable width, for a form or a
 * list. « columns »: cards that stand on their own; from xl they flow into two columns (down the
 * first, then the second, as they are read), so a wide screen is used rather than left empty. What
 * concerns the whole page (the way back, a period for every chart) goes in `top`, above both columns.
 */
withDefaults(defineProps<{ layout?: 'reading' | 'columns' }>(), { layout: 'reading' })
</script>

<template>
  <div
    v-if="layout === 'columns'"
    class="flex flex-col gap-4 xl:gap-6"
  >
    <slot name="top" />
    <div class="flex flex-col gap-4 xl:block xl:columns-2 xl:gap-6 xl:*:mb-6 xl:*:break-inside-avoid">
      <slot />
    </div>
  </div>
  <div
    v-else
    class="flex max-w-reading flex-col gap-4"
  >
    <slot name="top" />
    <slot />
  </div>
</template>
