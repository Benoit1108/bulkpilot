<script setup lang="ts">
import { Dumbbell } from 'lucide-vue-next'

definePageMeta({ title: 'Training' })
</script>

<template>
  <UiCard>
    <UiEmptyState
      :icon="Dumbbell"
      title="Bientôt disponible"
      description="Programmes, séances, séries, timer de repos et records."
    />
  </UiCard>
</template>
