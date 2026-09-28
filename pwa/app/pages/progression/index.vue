<script setup lang="ts">
import { ChartLine } from 'lucide-vue-next'

definePageMeta({ title: 'Progression' })
</script>

<template>
  <UiCard>
    <UiEmptyState
      :icon="ChartLine"
      title="Bientôt disponible"
      description="Pesées, moyennes, mensurations, photos et graphiques."
    />
  </UiCard>
</template>
