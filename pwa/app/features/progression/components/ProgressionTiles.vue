<script setup lang="ts">
import { formatGrams, formatKcal } from '~/features/nutrition/format'
import { weekStart } from '~/services/calendar/days'
import { counted } from '~/services/text/plural'
import { useIntakeOf, useMeasurementsOf, type useTrainingWeek } from '../data'
import { endedWeeks, formatCm, formatSignedCm, formatTonnage, periodLong } from '../format'
import { measureName, type Period } from '../progression'

/**
 * One figure per tile (spec §13 in Apple Health's way): the daily calories and protein over the
 * period, what the last full week lifted, a measurement. Each tile loads on its own. The lines
 * draw the ended weeks only (the week under way would make them fall); the figures come from the
 * API. The training week is the page's (it shows its sets by muscle too).
 */
const props = defineProps<{ period: Period, today: string, week: ReturnType<typeof useTrainingWeek> }>()

const period = toRef(props, 'period')
const intake = useIntakeOf(period, props.today)
const measurements = useMeasurementsOf(period, props.today)
const thisWeek = weekStart(props.today)
const during = computed(() => periodLong(period.value))

const history = computed(() => intake.data.value)
const intakeWeeks = computed(() => endedWeeks(history.value?.weeks ?? [], thisWeek))
const lastWeek = computed(() => props.week.data.value?.lastWeek ?? null)
/** The first measure that has a value: the one the user takes most often comes first. */
const measure = computed(() => measurements.data.value?.measures.find(series => series.latest) ?? null)

const lines = computed(() => ({
  energy: intakeWeeks.value.map(week => week.averageEnergyKcal),
  protein: intakeWeeks.value.map(week => week.averageProteinGrams),
  measure: measure.value?.points.map(point => point.cm) ?? [],
}))
/** The period is the picker's: the note says only what the figure is measured against. */
const averageNote = (target: string | null) => (target ? `Objectif ${target}` : 'Moyenne des jours saisis')

const loading = (data: { status: Ref<string>, data: Ref<unknown> }) => data.status.value === 'pending' && !data.data.value
const failed = (data: { error: Ref<unknown>, data: Ref<unknown> }) => Boolean(data.error.value) && !data.data.value
</script>

<template>
  <!-- Two by row from 360px: below, a figure and its unit would not fit half the width. -->
  <div class="grid grid-cols-1 gap-4 min-[360px]:grid-cols-2 lg:grid-cols-4 lg:gap-6">
    <UiTile
      title="Calories par jour"
      to="/progression/analyses"
      :loading="loading(intake)"
      :failed="failed(intake)"
      @retry="intake.refresh()"
    >
      <template v-if="history?.averageEnergyKcal != null">
        <p class="text-2xl font-bold tabular-nums">
          {{ formatKcal(history.averageEnergyKcal) }}
        </p>
        <UiSparkline :values="lines.energy" />
        <p class="text-sm text-muted">
          {{ averageNote(history.target && formatKcal(history.target.energyKcal)) }}
        </p>
      </template>
      <p
        v-else
        class="text-sm text-muted"
      >
        Aucun jour saisi {{ during }}.
      </p>
    </UiTile>
    <UiTile
      title="Protéines par jour"
      to="/progression/analyses"
      :loading="loading(intake)"
      :failed="failed(intake)"
      @retry="intake.refresh()"
    >
      <template v-if="history?.averageProteinGrams != null">
        <p class="text-2xl font-bold tabular-nums">
          {{ formatGrams(history.averageProteinGrams) }}
        </p>
        <UiSparkline :values="lines.protein" />
        <p class="text-sm text-muted">
          {{ averageNote(history.target && formatGrams(history.target.proteinGrams)) }}
        </p>
      </template>
      <p
        v-else
        class="text-sm text-muted"
      >
        Aucun jour saisi {{ during }}.
      </p>
    </UiTile>
    <UiTile
      title="Tonnage de la semaine dernière"
      to="/progression/analyses"
      :loading="loading(week)"
      :failed="failed(week)"
      @retry="week.refresh()"
    >
      <template v-if="lastWeek?.sessions">
        <p class="text-2xl font-bold tabular-nums">
          {{ formatTonnage(lastWeek.tonnageKg) }}
        </p>
        <p class="text-sm text-muted">
          En {{ counted(lastWeek.sessions, 'séance') }}, {{ counted(lastWeek.workingSets, 'série') }} de travail
        </p>
      </template>
      <p
        v-else
        class="text-sm text-muted"
      >
        Aucune séance la semaine dernière.
      </p>
    </UiTile>
    <UiTile
      :title="measure ? measureName(measure) : 'Mensurations'"
      to="/progression/mensurations"
      :loading="loading(measurements)"
      :failed="failed(measurements)"
      @retry="measurements.refresh()"
    >
      <template v-if="measure?.latest">
        <p class="text-2xl font-bold tabular-nums">
          {{ formatCm(measure.latest.cm) }}
        </p>
        <UiSparkline :values="lines.measure" />
        <p
          v-if="measure.changeCm !== null"
          class="text-sm text-muted"
        >
          {{ formatSignedCm(measure.changeCm) }} {{ during }}
        </p>
      </template>
      <p
        v-else
        class="text-sm text-muted"
      >
        Aucune mesure pour l’instant.
      </p>
    </UiTile>
  </div>
</template>
