<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'
import AdherenceCard from '~/features/coach/components/AdherenceCard.vue'
import RecommendationCard from '~/features/coach/components/RecommendationCard.vue'
import WeeklyReportCard from '~/features/coach/components/WeeklyReportCard.vue'
import { COACH_KEYS, type Decision, type Recommendation } from '~/features/coach/coach'
import { useCoachOfDay } from '~/features/coach/coachOfDay'
import { decisionNotice } from '~/features/coach/format'
import { isTargetDerived } from '~/features/nutrition/cacheKeys'
import { formatFullDay } from '~/services/calendar/days'
import { counted } from '~/services/text/plural'

definePageMeta({ title: 'Coach' })

const api = useApi()
// The Coach is the one of the user's today, past midnight too.
const today = useToday()

const { coach, error, refresh, analyse } = useCoachOfDay(() => today.value)

/**
 * One write at a time, for the whole page (an answer, « Relancer »): each reads the Coach back, and
 * two in flight would each show a list the other one changed.
 */
const writing = useSubmission()
const decidingId = ref<string | null>(null)

/** Announced even when it is the same as the last one (two « Plus tard » in a row). */
const notice = ref<string | null>(null)
async function announce(message: string): Promise<void> {
  notice.value = null
  await nextTick()
  notice.value = message
}

async function analyseAgain(): Promise<void> {
  decidingId.value = null
  const done = await writing.run(async () => {
    coach.value = await analyse(true)
  })
  const count = coach.value?.recommendations.length ?? 0
  if (done) await announce(count ? `Analyse relancée\u00A0: ${counted(count, 'recommandation')}.` : 'Analyse relancée\u00A0: rien à signaler.')
}

// By id: the order of a v-for ref array is not the list's.
/** The failure of an answer shows in its card; the others (« Relancer »), above the list. */
const messageFor = (recommendation: Recommendation) => (decidingId.value === recommendation.id ? writing.message.value : null)

const cards = new Map<string, InstanceType<typeof RecommendationCard>>()
function keep(id: string, card: unknown): void {
  if (card) cards.set(id, card as InstanceType<typeof RecommendationCard>)
  else cards.delete(id)
}
const heading = ref<HTMLElement | null>(null)
async function decide(index: number, recommendation: Recommendation, decision: Decision): Promise<void> {
  decidingId.value = recommendation.id
  const answer: { decided?: Recommendation } = {}
  const done = await writing.run(async () => {
    answer.decided = await api.post('/api/coach/recommendations/{id}/decision', { decision, today: today.value }, { path: { id: recommendation.id } })
    clearNuxtData(COACH_KEYS.history)
    // An accepted calorie change is a new nutrition target, from tomorrow.
    if (decision === 'accept' && recommendation.proposal) clearNuxtData(isTargetDerived)
    // Read again in place: the one decided on leaves, the others stay.
    coach.value = await api.get('/api/coach', { query: { today: today.value } })
  })
  if (!done || !answer.decided) return
  decidingId.value = null
  await announce(decisionNotice(answer.decided, decision))
  await nextTick()
  // Focus moves to the recommendation that took its place (or the one before), else to the heading.
  const shown = coach.value?.recommendations ?? []
  const next = shown[Math.min(index, shown.length - 1)]
  if (next) cards.get(next.id)?.focus()
  else heading.value?.focus()
}

/** Nothing recorded yet: the Coach says what it is before what it found. */
const firstRun = computed(() => !coach.value?.report && (coach.value?.adherence?.score ?? null) === null)
const analysedLabel = computed(() => {
  const day = coach.value?.analysedOn
  if (!day) return null
  return day === today.value ? 'Analysé aujourd’hui' : `Analysé le ${formatFullDay(day)}`
})
const links = [
  { to: '/coach/historique', title: 'Historique du Coach', summary: 'Les recommandations passées et ta réponse' },
  { to: '/coach/rapports', title: 'Rapports de la semaine', summary: 'Le bilan de chaque semaine' },
  { to: '/coach/reglages', title: 'Préférences du Coach', summary: 'Personnalité, ton, rythme d’analyse' },
]
</script>

<template>
  <UiPage>
    <UiLoadError
      v-if="error && !coach"
      @retry="refresh()"
    />
    <template v-else-if="coach">
      <p
        v-if="firstRun"
        class="text-muted"
      >
        Le Coach analyse tes données chaque jour. Rien ne change sans ton accord.
      </p>
      <section
        class="flex flex-col gap-3"
        aria-labelledby="coach-recommendations"
      >
        <h2
          id="coach-recommendations"
          ref="heading"
          class="text-lg font-semibold tracking-snug"
          tabindex="-1"
        >
          Recommandations
        </h2>
        <UiAlert
          v-if="writing.message.value && !decidingId"
          tone="error"
        >
          {{ writing.message.value }}
        </UiAlert>
        <RecommendationCard
          v-for="(recommendation, i) in coach.recommendations"
          :key="recommendation.id"
          :ref="card => keep(recommendation.id, card)"
          :recommendation="recommendation"
          :busy="writing.pending.value"
          :message="messageFor(recommendation)"
          :lead="i === 0"
          @decide="decision => decide(i, recommendation, decision)"
        />
        <UiCard v-if="!coach.recommendations.length">
          <UiEmptyState
            :icon="Sparkles"
            title="Rien à signaler&nbsp;: continue ton plan."
            description="Le Coach analyse tes données chaque jour et te propose au plus trois recommandations, toujours soumises à ta validation."
          />
        </UiCard>
        <div class="flex flex-wrap items-center gap-x-3 text-sm text-muted">
          <span v-if="analysedLabel">{{ analysedLabel }}.</span>
          <UiButton
            variant="ghost"
            :loading="writing.pending.value"
            @click="analyseAgain"
          >
            Relancer l’analyse
          </UiButton>
        </div>
      </section>
      <UiCard title="Adhérence">
        <AdherenceCard
          :adherence="coach.adherence"
          span="Sur les 7 derniers jours, aujourd’hui à part."
        />
      </UiCard>
      <WeeklyReportCard
        v-if="coach.report"
        :report="coach.report"
        :today="today"
      />
    </template>
    <p
      v-else
      class="text-muted"
      role="status"
    >
      Chargement…
    </p>
    <UiNavList
      label="Le Coach"
      :items="links"
    />
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </UiPage>
</template>
