<script setup lang="ts">
import TargetEstimator from '~/features/nutrition/components/TargetEstimator.vue'
import TargetHistory from '~/features/nutrition/components/TargetHistory.vue'
import { isJournalDerived, NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'

definePageMeta({ title: 'Objectifs' })

const { account } = useAccount()
const api = useApi()
const { data: history, error, refresh } = useAsyncData(NUTRITION_KEYS.targets, () => api.get('/api/nutrition/targets'))
async function onSaved(): Promise<void> {
  clearNuxtData(isJournalDerived)
  await refresh()
}
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/nutrition"
      label="Nutrition"
    />
    <UiCard>
      <TargetEstimator
        :account="account"
        @saved="onSaved"
      />
    </UiCard>
    <UiCard title="Historique">
      <UiAlert
        v-if="error && !history"
        tone="error"
      >
        Impossible de charger l’historique.
      </UiAlert>
      <TargetHistory
        v-else-if="history"
        :targets="history"
      />
      <p
        v-else
        class="muted"
      >
        Chargement…
      </p>
    </UiCard>
  </div>
</template>

<style scoped>
.muted {
  color: var(--color-text-muted);
}
</style>
