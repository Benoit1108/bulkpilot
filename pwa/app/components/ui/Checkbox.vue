<script setup lang="ts">
const model = defineModel<boolean>({ default: false })

const props = defineProps<{
  label: string
  hint?: string
  /** Part of a group in error: said on the box itself (a fieldset's description is poorly read). */
  invalid?: boolean
  /** The id of that error, read with the box. */
  describedby?: string
}>()

const id = useId()
const field = ref<HTMLInputElement | null>(null)
const described = computed(() => [props.hint ? `${id}-hint` : null, props.describedby ?? null].filter(Boolean).join(' ') || undefined)

defineExpose({ focus: () => field.value?.focus() })
</script>

<template>
  <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-2">
    <input
      :id="id"
      ref="field"
      v-model="model"
      class="mx-0 my-3 size-5 accent-accent-fill"
      type="checkbox"
      :aria-invalid="invalid ? 'true' : undefined"
      :aria-describedby="described"
    >
    <label
      :for="id"
      class="flex min-h-tap cursor-pointer items-center"
    >{{ label }}</label>
    <p
      v-if="hint"
      :id="`${id}-hint`"
      class="col-start-2 text-sm text-muted"
    >
      {{ hint }}
    </p>
  </div>
</template>
