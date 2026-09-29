<script setup lang="ts">
import OwnMeasures from '~/features/progression/components/OwnMeasures.vue'
import { isMeasurementDerived, PROGRESSION_KEYS } from '~/features/progression/progression'

definePageMeta({ title: 'Mes mesures' })

const api = useApi()
const { data: measures, error, refresh } = useAsyncData(PROGRESSION_KEYS.measures, () => api.get('/api/progression/measures'))

async function onChanged(): Promise<void> {
  clearNuxtData(key => isMeasurementDerived(key) && key !== PROGRESSION_KEYS.measures)
  await refresh()
}
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/progression/mensurations"
      label="Mensurations"
    />
    <UiLoadError
      v-if="error && !measures"
      @retry="refresh()"
    />
    <UiCard v-else-if="measures">
      <p class="text-muted">
        Le cou, les épaules, la poitrine, les bras, la taille, les cuisses et les mollets sont toujours là. Ajoute ici les mesures qui te manquent.
      </p>
      <OwnMeasures
        :measures="measures"
        @changed="onChanged"
      />
    </UiCard>
  </div>
</template>
