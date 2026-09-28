<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'

definePageMeta({ title: 'Coach' })
</script>

<template>
  <UiCard>
    <UiEmptyState
      :icon="Sparkles"
      title="Bientôt disponible"
      description="Analyses et recommandations expliquées, toujours soumises à ta validation."
    />
  </UiCard>
</template>
