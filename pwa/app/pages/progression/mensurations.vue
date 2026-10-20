<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import MeasurementsForm from '~/features/progression/components/MeasurementsForm.vue'
import MeasuresChart from '~/features/progression/components/MeasuresChart.vue'
import { isMeasurementDerived, PROGRESSION_KEYS, type Period } from '~/features/progression/progression'
import { localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Mensurations' })

const api = useApi()
const today = localDay()
const period = ref<Period>('3m')
const { data: measures, error: measuresError, refresh: refreshMeasures } = useAsyncData(PROGRESSION_KEYS.measures, () => api.get('/api/progression/measures'))
const { data: summary, error, refresh } = useAsyncData(
  () => PROGRESSION_KEYS.measurements(period.value, today),
  () => api.get('/api/progression/measurements', { query: { period: period.value, today } }),
  { watch: [period] },
)

// The global « + » leads here to measure: the first measure takes the focus.
const form = ref<InstanceType<typeof MeasurementsForm> | null>(null)
useAddRequest('mensurations', form)

async function onSaved(): Promise<void> {
  // What this page shows is read again in place; the other periods are dropped.
  const shown = [PROGRESSION_KEYS.measures, PROGRESSION_KEYS.measurements(period.value, today)]
  clearNuxtData(key => isMeasurementDerived(key) && !shown.includes(key))
  await Promise.all([refresh(), refreshMeasures()])
}
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/progression"
      label="Progression"
    />
    <UiLoadError
      v-if="(error && !summary) || (measuresError && !measures)"
      @retry="refresh(); refreshMeasures()"
    />
    <template v-else-if="measures && summary">
      <UiCard title="Mesurer">
        <MeasurementsForm
          ref="form"
          :measures="measures"
          :series="summary.measures"
          @saved="onSaved"
        />
        <NuxtLink
          to="/progression/mesures"
          :class="[TEXT_LINK, 'text-sm']"
        >
          Ajouter ou retirer des mesures perso
        </NuxtLink>
      </UiCard>
      <UiCard title="Évolution">
        <MeasuresChart
          v-model:period="period"
          :series="summary.measures"
        />
      </UiCard>
    </template>
    <p
      v-else
      class="text-muted"
    >
      Chargement…
    </p>
  </div>
</template>
