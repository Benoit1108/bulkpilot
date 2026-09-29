<script setup lang="ts">
const model = defineModel<string | null>({ default: '' })

withDefaults(defineProps<{
  label: string
  hint?: string
  error?: string
  maxlength?: number
  rows?: number
}>(), { hint: undefined, error: undefined, maxlength: undefined, rows: 3 })

const id = useId()
</script>

<template>
  <div
    class="ui-textarea"
    :class="{ 'ui-textarea--invalid': error }"
  >
    <label
      :for="id"
      class="ui-textarea__label"
    >{{ label }}</label>
    <textarea
      :id="id"
      v-model="model"
      class="ui-textarea__field"
      :rows="rows"
      :maxlength="maxlength"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="[hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined"
    />
    <p
      v-if="hint"
      :id="`${id}-hint`"
      class="ui-textarea__hint"
    >
      {{ hint }}
    </p>
    <p
      v-if="error"
      :id="`${id}-error`"
      class="ui-textarea__error"
    >
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.ui-textarea {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.ui-textarea__label {
  font-size: var(--text-sm);
  font-weight: 600;
}

.ui-textarea__field {
  padding: var(--space-2) var(--space-3);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: inherit;
  /* 16px minimum: iOS zooms into smaller inputs. */
  font: inherit;
  font-size: max(1rem, var(--text-md));
  resize: vertical;
}

.ui-textarea__field:focus {
  border-color: var(--color-accent);
  outline: 2px solid var(--color-accent);
  outline-offset: 1px;
}

.ui-textarea--invalid .ui-textarea__field {
  border-color: var(--color-danger);
}

.ui-textarea__hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.ui-textarea__error {
  color: var(--color-danger);
  font-size: var(--text-sm);
}
</style>
