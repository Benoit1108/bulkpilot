<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

// Only a user-facing message: technical details stay in the console and server logs.
const message = computed(() =>
  props.error.statusCode === 404 ? 'Cette page n’existe pas.' : 'Une erreur inattendue est survenue.',
)

useHead({ title: 'Erreur' })
</script>

<template>
  <main class="error-page">
    <h1 class="error-page__title">
      {{ message }}
    </h1>
    <UiButton @click="clearError({ redirect: '/' })">
      Retour à l’accueil
    </UiButton>
  </main>
</template>

<style scoped>
.error-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-5);
  min-height: 100dvh;
  padding: var(--space-5);
  text-align: center;
}

.error-page__title {
  font-size: var(--text-lg);
  font-weight: 600;
}
</style>
