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
    class="ui-card"
    :aria-labelledby="title && as !== 'div' ? titleId : undefined"
  >
    <header
      v-if="title || $slots.actions"
      class="ui-card__header"
    >
      <h2
        v-if="title"
        :id="titleId"
        class="ui-card__title"
      >
        {{ title }}
      </h2>
      <slot name="actions" />
    </header>
    <slot />
  </component>
</template>

<style scoped>
.ui-card {
  scroll-margin-top: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.ui-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.ui-card__title {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
</style>
