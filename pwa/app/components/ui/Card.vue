<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  as?: 'section' | 'article' | 'div'
}>(), {
  title: undefined,
  as: 'section',
})

const titleId = useId()
</script>

<template>
  <component
    :is="as"
    class="flex scroll-mt-8 flex-col gap-3 rounded-lg border border-line bg-surface p-4"
    :aria-labelledby="title && as !== 'div' ? titleId : undefined"
  >
    <header
      v-if="title || $slots.actions"
      class="flex items-center justify-between gap-3"
    >
      <h2
        v-if="title"
        :id="titleId"
        class="text-sm font-semibold tracking-caps text-muted uppercase"
      >
        {{ title }}
      </h2>
      <slot name="actions" />
    </header>
    <slot />
  </component>
</template>
