<script setup lang="ts">
import CaloriesVsWeight from '~/features/progression/components/CaloriesVsWeight.vue'
import IntakeCharts from '~/features/progression/components/IntakeCharts.vue'
import PeriodPicker from '~/features/progression/components/PeriodPicker.vue'
import TrainingSummary from '~/features/progression/components/TrainingSummary.vue'
import MuscleVolume from '~/features/training/components/MuscleVolume.vue'
import { periodLong } from '~/features/progression/format'
import { useIntakeOf, useTrainingOf, useTrainingWeek, useWeightOf } from '~/features/progression/data'
import type { Period } from '~/features/progression/progression'
import { localDay, weekStart } from '~/services/calendar/days'

definePageMeta({ title: 'Analyses' })

const today = localDay()
/** The week under way, as the API keys weeks: drawn only once it is over. */
const thisWeek = weekStart(today)
const period = ref<Period>('3m')
const during = computed(() => periodLong(period.value))
const { data: intake, error: intakeError, refresh: refreshIntake } = useIntakeOf(period, today)
const { data: weight, error: weightError, refresh: refreshWeight } = useWeightOf(period, today)
const { data: training, error: trainingError, refresh: refreshTraining } = useTrainingOf(period, today)
const { data: week, error: weekError, refresh: refreshWeek } = useTrainingWeek(today)
</script>

<template>
  <UiPage layout="columns">
    <template #top>
      <ShellBackLink
        to="/progression"
        label="Progression"
      />
      <PeriodPicker
        v-model="period"
        name="analytics-period"
      />
    </template>
    <!-- Each chart keeps about its room while it loads: the cards below do not jump (layout shift). -->
    <UiCard title="Calories face au poids">
      <UiLoadError
        v-if="(intakeError && !intake) || (weightError && !weight)"
        @retry="refreshIntake(); refreshWeight()"
      />
      <CaloriesVsWeight
        v-else-if="intake && weight"
        :weights="weight.weeks"
        :intakes="intake.weeks"
        :this-week="thisWeek"
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
        :this-week="thisWeek"
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
        :this-week="thisWeek"
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
    <UiCard title="Séries par muscle, cette semaine">
      <UiLoadError
        v-if="weekError && !week"
        @retry="refreshWeek()"
      />
      <MuscleVolume
        v-else-if="week"
        :muscles="week.muscles"
      />
      <p
        v-else
        class="min-h-40 text-muted"
        role="status"
        aria-busy="true"
      >
        Chargement…
      </p>
    </UiCard>
  </UiPage>
</template>
