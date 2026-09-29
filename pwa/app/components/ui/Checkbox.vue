<script setup lang="ts">
const model = defineModel<boolean>({ default: false })

defineProps<{ label: string, hint?: string }>()

const id = useId()
</script>

<template>
  <div class="ui-checkbox">
    <input
      :id="id"
      v-model="model"
      class="ui-checkbox__box"
      type="checkbox"
      :aria-describedby="hint ? `${id}-hint` : undefined"
    >
    <label
      :for="id"
      class="ui-checkbox__label"
    >{{ label }}</label>
    <p
      v-if="hint"
      :id="`${id}-hint`"
      class="ui-checkbox__hint"
    >
      {{ hint }}
    </p>
  </div>
</template>

<style scoped>
.ui-checkbox {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  column-gap: var(--space-2);
}

.ui-checkbox__box {
  width: 1.25rem;
  height: 1.25rem;
  margin: calc((var(--tap-target) - 1.25rem) / 2) 0;
  accent-color: var(--color-accent-fill);
}

.ui-checkbox__label {
  display: flex;
  align-items: center;
  min-height: var(--tap-target);
  cursor: pointer;
}

.ui-checkbox__hint {
  grid-column: 2;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
