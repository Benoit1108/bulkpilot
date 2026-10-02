<script setup lang="ts">
import { Eye, EyeOff } from 'lucide-vue-next'
import { fieldClass } from './classes'

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
const describedBy = computed(() => [props.suffix && `${id}-suffix`, props.hint && `${id}-hint`, props.error && `${id}-error`].filter(Boolean).join(' ') || undefined)
// Numbers are typed as text with a numeric keyboard: a native number field drops "12,5" typed
// with a French keyboard on some phones. parseAmount() reads both "12,5" and "12.5".
const nativeType = computed(() => (props.type === 'number' ? 'text' : actualType.value))
const keyboard = computed(() => props.inputmode ?? (props.type === 'number' ? 'decimal' : undefined))
</script>

<template>
  <div :class="fieldClass.root">
    <label
      :for="id"
      :class="fieldClass.label"
    >{{ label }}</label>
    <!-- While the reveal button has the focus, only its own ring shows (not the field's too). -->
    <div
      class="flex min-h-tap items-center rounded-md border bg-surface-raised transition-colors focus-within:border-accent focus-within:outline-2 focus-within:outline-offset-1 focus-within:outline-accent has-[button:focus-visible]:outline-none"
      :class="error ? 'border-danger focus-within:border-danger' : 'border-line'"
    >
      <!-- 16px minimum (text-base): iOS zooms into smaller inputs. -->
      <input
        :id="id"
        ref="field"
        v-model="model"
        class="h-tap w-0 min-w-0 flex-1 bg-transparent px-3 text-base focus:outline-none"
        :type="nativeType"
        :autocomplete="autocomplete"
        :inputmode="keyboard"
        :required="required"
        :min="type === 'number' ? undefined : min"
        :max="type === 'number' ? undefined : max"
        :maxlength="maxlength"
        :pattern="pattern"
        :autocapitalize="literal ? 'off' : undefined"
        :spellcheck="literal ? false : undefined"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="describedBy"
      >
      <span
        v-if="suffix"
        :id="`${id}-suffix`"
        class="pr-3 text-muted"
      >{{ suffix }}</span>
      <button
        v-if="type === 'password'"
        type="button"
        class="grid size-tap cursor-pointer place-items-center bg-transparent p-0 text-muted focus-visible:rounded-sm focus-visible:-outline-offset-4"
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
      :class="fieldClass.hint"
    >
      {{ hint }}
    </p>
    <p
      v-if="error"
      :id="`${id}-error`"
      :class="fieldClass.error"
    >
      {{ error }}
    </p>
  </div>
</template>
