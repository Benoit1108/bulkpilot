<script setup lang="ts">
import { Utensils } from 'lucide-vue-next'

definePageMeta({ title: 'Nutrition' })
</script>

<template>
  <UiCard>
    <UiEmptyState
      :icon="Utensils"
      title="Bientôt disponible"
      description="Journal alimentaire, recettes, scan de produits et Meal Builder."
    />
  </UiCard>
</template>
