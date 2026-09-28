<script setup lang="ts" generic="T extends string">
const model = defineModel<T | null>({ default: null })

withDefaults(defineProps<{
  label: string
  options: ReadonlyArray<{ value: T, label: string }>
  placeholder?: string
  error?: string
}>(), {
  placeholder: 'Non renseigné',
  error: undefined,
})

const id = useId()
</script>

<template>
  <div class="ui-select">
    <label
      :for="id"
      class="ui-select__label"
    >{{ label }}</label>
    <select
      :id="id"
      v-model="model"
      class="ui-select__field"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="error ? `${id}-error` : undefined"
    >
      <option :value="null">
        {{ placeholder }}
      </option>
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>
    <p
      v-if="error"
      :id="`${id}-error`"
      class="ui-select__error"
    >
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.ui-select {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.ui-select__label {
  font-size: var(--text-sm);
  font-weight: 600;
}

.ui-select__field {
  height: var(--tap-target);
  padding: 0 var(--space-3);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: max(1rem, var(--text-md));
}

.ui-select__field:focus-visible {
  border-color: var(--color-accent);
}

.ui-select__error {
  color: var(--color-danger);
  font-size: var(--text-sm);
}
</style>
