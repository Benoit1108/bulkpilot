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
  <main class="flex min-h-dvh flex-col items-center justify-center gap-6 p-6 text-center">
    <h1 class="text-lg font-semibold">
      {{ message }}
    </h1>
    <UiButton @click="clearError({ redirect: '/' })">
      Retour à l’accueil
    </UiButton>
  </main>
</template>
