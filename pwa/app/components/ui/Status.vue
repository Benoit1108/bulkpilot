<script setup lang="ts">
/**
 * A short confirmation ("Poulet ajouté au déjeuner"), visible and announced by screen readers.
 * The region stays mounted (empty) so that assistive technologies notice each new message.
 */
const props = withDefaults(defineProps<{ message: string | null, duration?: number }>(), { duration: 5000 })
const emit = defineEmits<{ dismiss: [] }>()

let timer: ReturnType<typeof setTimeout> | undefined
watch(() => props.message, (message) => {
  clearTimeout(timer)
  if (message) timer = setTimeout(() => emit('dismiss'), props.duration)
})
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <!-- Above the bottom navigation on phones. -->
  <div
    data-testid="status"
    class="pointer-events-none fixed inset-x-4 bottom-above-nav z-30 flex justify-center md:bottom-safe-6"
    role="status"
  >
    <p
      v-if="message"
      class="max-w-reading rounded-md border border-line bg-surface-raised px-4 py-3 font-semibold shadow-raised"
    >
      {{ message }}
    </p>
  </div>
</template>
