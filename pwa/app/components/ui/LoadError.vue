<script setup lang="ts">
/** A list or page that could not be loaded (offline at the gym…), with a way to try again. */
withDefaults(defineProps<{
  message?: string
  /**
   * False where several can appear at once (dashboard widgets): announced politely, one after the
   * other, and the focus stays put.
   */
  focusOnMount?: boolean
}>(), { message: 'Impossible de charger ces données. Vérifie ta connexion.', focusOnMount: true })
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
