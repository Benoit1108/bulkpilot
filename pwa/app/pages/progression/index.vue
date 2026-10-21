<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import { Scale, Target } from 'lucide-vue-next'
import GoalCard from '~/features/progression/components/GoalCard.vue'
import PeriodPicker from '~/features/progression/components/PeriodPicker.vue'
import ProgressionTiles from '~/features/progression/components/ProgressionTiles.vue'
import WeighInForm from '~/features/progression/components/WeighInForm.vue'
import WeightChart from '~/features/progression/components/WeightChart.vue'
import WeightFigures from '~/features/progression/components/WeightFigures.vue'
import { useTrainingWeek, useWeightOf } from '~/features/progression/data'
import { formatKg, formatTrend } from '~/features/progression/format'
import { isWeightDerived, PROGRESSION_KEYS, type Period } from '~/features/progression/progression'
import MuscleVolume from '~/features/training/components/MuscleVolume.vue'
import { formatFullDay, localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Progression' })

const today = localDay()
const period = ref<Period>('3m')
const { data: summary, error, refresh, pending } = useWeightOf(period, today)
const week = useTrainingWeek(today)

/** The latest weigh-in, whatever the period: none means a first visit. */
const latest = computed(() => summary.value?.latest ?? null)
/** The trend is over 4 weeks, whatever the period picked (without enough weigh-ins, it says why). */
const headline = computed(() => {
  if (!summary.value || !latest.value) return ''
  const trend = summary.value.kgPerWeek === null ? formatTrend(summary.value) : `sur 4 semaines\u00A0: ${formatTrend(summary.value)}`
  return `le ${formatFullDay(latest.value.day)} · ${trend}`
})

/** The weigh-in is a panel: the page stays a dashboard (the global « + » opens it too). */
const route = useRoute()
const weighing = ref(route.query.ajouter === 'pesee')
watch(() => route.query.ajouter, (asked) => {
  if (asked === 'pesee') weighing.value = true
})
const weighInForm = ref<InstanceType<typeof WeighInForm> | null>(null)
useAddRequest('pesee', weighInForm)

const notice = ref<string | null>(null)
async function onSaved(message: string): Promise<void> {
  weighing.value = false
  notice.value = message
  // The chart shown is read again in place; the other periods and pages are dropped.
  const shown = PROGRESSION_KEYS.weight(period.value, today)
  clearNuxtData(key => isWeightDerived(key) && key !== shown)
  await refresh()
}

const links = [
  { to: '/progression/mensurations', title: 'Mensurations', summary: 'Bras, taille, cuisses… et tes mesures perso' },
  { to: '/progression/photos', title: 'Photos', summary: 'Tes sessions, comparées côte à côte' },
  { to: '/progression/analyses', title: 'Analyses', summary: 'Calories face au poids, protéines, volume d’entraînement' },
  { to: '/progression/pesees', title: 'Toutes les pesées', summary: 'Par mois, corriger ou retirer une pesée' },
]
</script>

<template>
  <UiPage layout="wide">
    <template #top>
      <!-- On a phone the six periods take the width; the action goes under them. -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <PeriodPicker
          v-model="period"
          name="weight-period"
        />
        <UiButton
          class="self-start"
          @click="weighing = true"
        >
          Ajouter une pesée
        </UiButton>
      </div>
    </template>
    <!-- The weight and its goal wait for the weight; every other block loads on its own. On a phone,
         the tiles come right after the weight, the goal after them. -->
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
      <template v-if="summary">
        <UiCard
          title="Poids"
          class="lg:col-span-2"
          :aria-busy="pending || undefined"
        >
          <template v-if="latest">
            <p class="flex flex-wrap items-baseline gap-x-3">
              <span class="text-figure font-bold tabular-nums">{{ formatKg(latest.weightKg) }}</span>
              <span class="text-sm text-muted">{{ headline }}</span>
            </p>
            <WeightChart
              :summary="summary"
              :period="period"
            />
            <WeightFigures :summary="summary" />
          </template>
          <UiEmptyState
            v-else
            :icon="Scale"
            title="Aucune pesée pour l’instant"
            description="Pèse-toi le matin, à jeun&nbsp;: ta première pesée lance le suivi, les moyennes et la tendance."
          />
        </UiCard>
        <UiCard
          title="Objectif"
          class="max-lg:order-1"
        >
          <template #actions>
            <NuxtLink
              v-if="summary.goal"
              to="/progression/objectif"
              :class="[TEXT_LINK, 'text-sm']"
            >
              Modifier<span class="sr-only"> l’objectif</span>
            </NuxtLink>
          </template>
          <GoalCard
            v-if="summary.goal"
            :goal="summary.goal"
          />
          <UiEmptyState
            v-else-if="latest"
            :icon="Target"
            title="Aucun objectif de poids"
            description="Un poids visé et des jalons en chemin, avec une estimation du temps qu’il reste au rythme actuel."
          >
            <UiLinkButton
              to="/progression/objectif"
              variant="secondary"
            >
              Fixer un objectif
            </UiLinkButton>
          </UiEmptyState>
          <UiEmptyState
            v-else
            :icon="Target"
            title="Aucun objectif de poids"
            description="Enregistre d’abord une pesée&nbsp;: elle sert de point de départ à l’objectif."
          />
        </UiCard>
      </template>
      <UiLoadError
        v-else-if="error"
        class="lg:col-span-3"
        @retry="refresh()"
      />
      <!-- About the weight's room is kept while it loads: what follows barely moves (layout shift). -->
      <p
        v-else
        class="min-h-128 text-muted lg:col-span-3"
        role="status"
        aria-busy="true"
      >
        Chargement…
      </p>
      <ProgressionTiles
        class="lg:col-span-3"
        :period="period"
        :today="today"
        :week="week"
      />
      <UiCard
        title="Séries par muscle, cette semaine"
        class="self-start max-lg:order-2"
      >
        <UiLoadError
          v-if="week.error.value && !week.data.value"
          @retry="week.refresh()"
        />
        <MuscleVolume
          v-else-if="week.data.value"
          :muscles="week.data.value.muscles"
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
      <UiNavList
        class="max-lg:order-2 lg:col-span-2"
        label="Suivi"
        :items="links"
      />
    </div>
    <UiDrawer
      v-model:open="weighing"
      title="Ajouter une pesée"
    >
      <WeighInForm
        ref="weighInForm"
        :latest="latest"
        @saved="onSaved"
      />
    </UiDrawer>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </UiPage>
</template>
