<script setup lang="ts">
const model = defineModel<boolean>({ default: false })

defineProps<{ label: string, hint?: string }>()

const id = useId()
</script>

<template>
  <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-2">
    <input
      :id="id"
      v-model="model"
      class="mx-0 my-3 size-5 accent-accent-fill"
      type="checkbox"
      :aria-describedby="hint ? `${id}-hint` : undefined"
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
