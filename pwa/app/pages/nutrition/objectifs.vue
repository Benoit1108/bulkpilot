<script setup lang="ts">
import TargetEstimator from '~/features/nutrition/components/TargetEstimator.vue'
import TargetHistory from '~/features/nutrition/components/TargetHistory.vue'
import { isJournalDerived, NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { targetInForce } from '~/features/nutrition/nutrition'
import { localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Objectifs' })

const { account } = useAccount()
const api = useApi()
const { data: history, error, refresh } = useAsyncData(NUTRITION_KEYS.targets, () => api.get('/api/nutrition/targets'))
const current = computed(() => (history.value ? targetInForce(history.value, localDay()) : null))
async function onSaved(): Promise<void> {
  clearNuxtData(isJournalDerived)
  await refresh()
}
</script>

<template>
  <UiPage layout="columns">
    <template #top>
      <ShellBackLink
        to="/nutrition"
        label="Nutrition"
      />
    </template>
    <UiCard>
      <TargetEstimator
        :account="account"
        :current="current"
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
        class="text-muted"
      >
        Chargement…
      </p>
    </UiCard>
  </UiPage>
</template>
