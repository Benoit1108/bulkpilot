<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import { Scale, Target } from 'lucide-vue-next'
import GoalCard from '~/features/progression/components/GoalCard.vue'
import WeighInForm from '~/features/progression/components/WeighInForm.vue'
import WeightChart from '~/features/progression/components/WeightChart.vue'
import WeightFigures from '~/features/progression/components/WeightFigures.vue'
import { isWeightDerived, PROGRESSION_KEYS, type Period } from '~/features/progression/progression'
import { localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Progression' })

const api = useApi()
const today = localDay()
const period = ref<Period>('3m')
const { data: summary, error, refresh, pending } = useAsyncData(
  () => PROGRESSION_KEYS.weight(period.value, today),
  () => api.get('/api/progression/weight', { query: { period: period.value, today } }),
  { watch: [period] },
)

// The latest weigh-in is the latest ever, whatever the period: none means a first visit.
const hasWeighIn = computed(() => Boolean(summary.value?.latest))

async function onSaved(): Promise<void> {
  // The chart shown is read again in place; the other periods and pages are dropped.
  const shown = PROGRESSION_KEYS.weight(period.value, today)
  clearNuxtData(key => isWeightDerived(key) && key !== shown)
  await refresh()
}

// The global « + » leads here to weigh in: the weight field takes the focus.
const weighInForm = ref<InstanceType<typeof WeighInForm> | null>(null)
useAddRequest('pesee', weighInForm)

const links = [
  { to: '/progression/mensurations', title: 'Mensurations', summary: 'Bras, taille, cuisses… et tes mesures perso' },
  { to: '/progression/photos', title: 'Photos', summary: 'Tes sessions, comparées côte à côte' },
  { to: '/progression/analyses', title: 'Analyses', summary: 'Calories face au poids, protéines, volume d’entraînement' },
  { to: '/progression/pesees', title: 'Toutes les pesées', summary: 'Corriger ou retirer une pesée' },
]
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <UiLoadError
      v-if="error && !summary"
      @retry="refresh()"
    />
    <template v-else>
      <UiCard title="Pesée">
        <WeighInForm
          ref="weighInForm"
          :latest="summary?.latest ?? null"
          @saved="onSaved"
        />
      </UiCard>
      <UiCard
        v-if="summary"
        title="Poids"
        :aria-busy="pending || undefined"
      >
        <template v-if="hasWeighIn">
          <WeightFigures :summary="summary" />
          <WeightChart
            v-model:period="period"
            :summary="summary"
          />
        </template>
        <UiEmptyState
          v-else
          :icon="Scale"
          title="Aucune pesée pour l’instant"
          description="Pèse-toi le matin, à jeun&nbsp;: ta première pesée lance le suivi, les moyennes et la tendance."
        />
      </UiCard>
      <UiCard
        v-if="summary"
        title="Objectif"
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
          v-else-if="hasWeighIn"
          :icon="Target"
          title="Aucun objectif de poids"
          description="Un poids visé et des jalons en chemin, avec une estimation du temps qu’il reste au rythme actuel."
        >
          <UiLinkButton to="/progression/objectif">
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
      <!-- About the summary's room is kept while it loads: the list below barely moves (layout shift). -->
      <p
        v-else
        class="min-h-128 text-muted"
        role="status"
        aria-busy="true"
      >
        Chargement…
      </p>
      <UiNavList
        label="Suivi"
        :items="links"
      />
    </template>
  </div>
</template>
