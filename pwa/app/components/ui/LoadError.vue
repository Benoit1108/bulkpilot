<script setup lang="ts">
/** A list or page that could not be loaded (offline at the gym…), with a way to try again. */
withDefaults(defineProps<{ message?: string }>(), { message: 'Impossible de charger ces données. Vérifie ta connexion.' })
const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <UiAlert tone="error">
    {{ message }}
    <UiButton
      variant="ghost"
      @click="emit('retry')"
    >
      Réessayer
    </UiButton>
  </UiAlert>
</template>
