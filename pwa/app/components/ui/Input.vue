<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next'

const model = defineModel<string | number | null>({ default: '' })

const props = withDefaults(defineProps<{
  label: string
  type?: 'text' | 'email' | 'password' | 'number' | 'date'
  autocomplete?: string
  inputmode?: 'text' | 'email' | 'numeric' | 'decimal'
  error?: string
  hint?: string
  required?: boolean
  min?: number | string
  max?: number | string
  suffix?: string
  maxlength?: number
  pattern?: string
  /** Focused when it appears (first field of a new step). */
  autofocus?: boolean
  /** Codes, identifiers: no auto-capitalisation or spell-check on phones. */
  literal?: boolean
}>(), {
  type: 'text',
  autocomplete: 'off',
  inputmode: undefined,
  error: undefined,
  hint: undefined,
  required: false,
  min: undefined,
  max: undefined,
  suffix: undefined,
  maxlength: undefined,
  pattern: undefined,
  autofocus: false,
  literal: false,
})

const id = useId()
const revealed = ref(false)
const field = ref<HTMLInputElement | null>(null)
onMounted(() => {
  if (props.autofocus) field.value?.focus()
})

defineExpose({ focus: () => field.value?.focus() })
const actualType = computed(() => (props.type === 'password' && revealed.value ? 'text' : props.type))
const describedBy = computed(() => [props.hint && `${id}-hint`, props.error && `${id}-error`].filter(Boolean).join(' ') || undefined)
</script>

<template>
  <div
    class="ui-input"
    :class="{ 'ui-input--invalid': error }"
  >
    <label
      :for="id"
      class="ui-input__label"
    >{{ label }}</label>
    <div class="ui-input__control">
      <input
        :id="id"
        ref="field"
        v-model="model"
        class="ui-input__field"
        :type="actualType"
        :autocomplete="autocomplete"
        :inputmode="inputmode"
        :required="required"
        :min="min"
        :max="max"
        :maxlength="maxlength"
        :pattern="pattern"
        :autocapitalize="literal ? 'off' : undefined"
        :spellcheck="literal ? false : undefined"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="describedBy"
      >
      <span
        v-if="suffix"
        class="ui-input__suffix"
        aria-hidden="true"
      >{{ suffix }}</span>
      <button
        v-if="type === 'password'"
        type="button"
        class="ui-input__reveal"
        :aria-label="revealed ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
        :aria-pressed="revealed"
        @click="revealed = !revealed"
      >
        <EyeOff
          v-if="revealed"
          :size="18"
          aria-hidden="true"
        />
        <Eye
          v-else
          :size="18"
          aria-hidden="true"
        />
      </button>
    </div>
    <p
      v-if="hint"
      :id="`${id}-hint`"
      class="ui-input__hint"
    >
      {{ hint }}
    </p>
    <p
      v-if="error"
      :id="`${id}-error`"
      class="ui-input__error"
    >
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.ui-input {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.ui-input__label {
  font-size: var(--text-sm);
  font-weight: 600;
}

.ui-input__control {
  display: flex;
  align-items: center;
  min-height: var(--tap-target);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  transition: border-color var(--transition-fast);
}

.ui-input__control:focus-within {
  border-color: var(--color-accent);
  outline: 2px solid var(--color-accent);
  outline-offset: 1px;
}

.ui-input--invalid .ui-input__control {
  border-color: var(--color-danger);
}

.ui-input__field {
  flex: 1;
  min-width: 0;
  height: var(--tap-target);
  padding: 0 var(--space-3);
  background: transparent;
  border: 0;
  /* 16px minimum: iOS zooms into smaller inputs. */
  font-size: max(1rem, var(--text-md));
}

.ui-input__field:focus {
  outline: none;
}

.ui-input__suffix {
  padding-right: var(--space-3);
  color: var(--color-text-muted);
}

.ui-input__reveal {
  display: grid;
  place-items: center;
  width: var(--tap-target);
  height: var(--tap-target);
  padding: 0;
  background: transparent;
  border: 0;
  color: var(--color-text-muted);
  cursor: pointer;
}

.ui-input__reveal:focus-visible {
  outline-offset: -4px;
  border-radius: var(--radius-sm);
}

/* The field's ring is enough while the reveal button has the focus. */
.ui-input__control:has(.ui-input__reveal:focus-visible) {
  outline: none;
}

.ui-input__hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.ui-input__error {
  color: var(--color-danger);
  font-size: var(--text-sm);
}
</style>
