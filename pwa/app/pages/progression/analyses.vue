<script setup lang="ts">
import CaloriesVsWeight from '~/features/progression/components/CaloriesVsWeight.vue'
import IntakeCharts from '~/features/progression/components/IntakeCharts.vue'
import PeriodPicker from '~/features/progression/components/PeriodPicker.vue'
import TrainingSummary from '~/features/progression/components/TrainingSummary.vue'
import { periodLong } from '~/features/progression/format'
import { PROGRESSION_KEYS, type Period } from '~/features/progression/progression'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Analyses' })

const api = useApi()
const today = localDay()
const period = ref<Period>('3m')
const during = computed(() => periodLong(period.value))
const query = () => ({ query: { period: period.value, today } })
// Each context computes its own figures; this page only sets them side by side.
const { data: intake, error: intakeError, refresh: refreshIntake } = useAsyncData(() => `nutrition-history-${period.value}-${today}`, () => api.get('/api/nutrition/history', query()), { watch: [period] })
const { data: weight, error: weightError, refresh: refreshWeight } = useAsyncData(() => PROGRESSION_KEYS.weight(period.value, today), () => api.get('/api/progression/weight', query()), { watch: [period] })
const { data: training, error: trainingError, refresh: refreshTraining } = useAsyncData(() => TRAINING_KEYS.analytics(period.value, today), () => api.get('/api/training/analytics', query()), { watch: [period] })
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <!-- Each chart keeps about its room while it loads: the cards below do not jump (layout shift). -->
    <ShellBackLink
      to="/progression"
      label="Progression"
    />
    <PeriodPicker
      v-model="period"
      name="analytics-period"
    />
    <UiCard title="Calories face au poids">
      <UiLoadError
        v-if="(intakeError && !intake) || (weightError && !weight)"
        @retry="refreshIntake(); refreshWeight()"
      />
      <CaloriesVsWeight
        v-else-if="intake && weight"
        :weights="weight.weeks"
        :intakes="intake.weeks"
      />
      <p
        v-else
        class="min-h-120 text-muted"
        role="status"
        aria-busy="true"
      >
        Chargement…
      </p>
    </UiCard>
    <UiCard title="Nutrition">
      <UiLoadError
        v-if="intakeError && !intake"
        @retry="refreshIntake()"
      />
      <IntakeCharts
        v-else-if="intake"
        :history="intake"
        :during="during"
      />
      <p
        v-else
        class="min-h-120 text-muted"
        role="status"
        aria-busy="true"
      >
        Chargement…
      </p>
    </UiCard>
    <UiCard title="Entraînement">
      <UiLoadError
        v-if="trainingError && !training"
        @retry="refreshTraining()"
      />
      <TrainingSummary
        v-else-if="training"
        :analytics="training"
        :during="during"
      />
      <p
        v-else
        class="min-h-120 text-muted"
        role="status"
        aria-busy="true"
      >
        Chargement…
      </p>
    </UiCard>
  </div>
</template>
