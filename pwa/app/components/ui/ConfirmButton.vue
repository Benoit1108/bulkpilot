<script setup lang="ts">
/**
 * A destructive action in two taps: the first one asks, the second one acts. Leaving the button
 * (blur, Escape) cancels, so a stray tap on a phone deletes nothing.
 */
const props = withDefaults(defineProps<{
  label: string
  confirmLabel?: string
  loading?: boolean
  variant?: 'secondary' | 'ghost'
}>(), { confirmLabel: 'Confirmer la suppression', loading: false, variant: 'secondary' })

const emit = defineEmits<{ confirm: [] }>()
const armed = ref(false)

function click(): void {
  if (props.loading) return
  if (!armed.value) {
    armed.value = true
    return
  }
  armed.value = false
  emit('confirm')
}
</script>

<template>
  <UiButton
    :variant="armed ? 'danger' : variant"
    :loading="loading"
    @click="click"
    @blur="armed = false"
    @keydown.esc="armed = false"
  >
    {{ armed ? confirmLabel : label }}
  </UiButton>
</template>
