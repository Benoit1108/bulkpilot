<script setup lang="ts">
import { LayoutDashboard } from 'lucide-vue-next'

definePageMeta({ title: 'Accueil' })

const dateFormat = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const now = ref(new Date())
const today = computed(() => dateFormat.format(now.value))

// An installed PWA can stay open overnight: refresh the date when it comes back to the foreground.
function refreshDate(): void {
  if (document.visibilityState === 'visible') now.value = new Date()
}
onMounted(() => document.addEventListener('visibilitychange', refreshDate))
onBeforeUnmount(() => document.removeEventListener('visibilitychange', refreshDate))
</script>

<template>
  <div class="page">
    <p class="today">
      {{ today }}
    </p>

    <UiCard>
      <UiEmptyState
        :icon="LayoutDashboard"
        title="Ton tableau de bord arrive"
        description="Il s’assemblera à partir de tes vraies données : pesées, repas, séances et recommandations."
      />
    </UiCard>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.today {
  color: var(--color-text-muted);
}

/* "Lundi 28 septembre": only the first letter is capitalised in French. */
.today::first-letter {
  text-transform: uppercase;
}
</style>
