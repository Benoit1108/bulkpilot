<script setup lang="ts">
/**
 * A list or page that could not be loaded, with a way to try again. The default text blames
 * neither the network nor the server: offline and an update restarting the service look alike here
 * (the connectivity banner says which).
 */
withDefaults(defineProps<{
  message?: string
  /**
   * False where several can appear at once (dashboard widgets): announced politely, one after the
   * other, and the focus stays put.
   */
  focusOnMount?: boolean
}>(), { message: 'Impossible de charger ces données. Réessaie dans un instant.', focusOnMount: true })
const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <UiAlert
    tone="error"
    :focus-on-mount="focusOnMount"
    :polite="!focusOnMount"
  >
    {{ message }}
    <UiButton
      variant="ghost"
      @click="emit('retry')"
    >
      Réessayer
    </UiButton>
  </UiAlert>
</template>
