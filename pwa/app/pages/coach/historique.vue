<script setup lang="ts">
import { History } from 'lucide-vue-next'
import CoachLevel from '~/features/coach/components/CoachLevel.vue'
import { COACH_KEYS, statusLabel, type Recommendation } from '~/features/coach/coach'
import { proposalSummary } from '~/features/coach/format'
import { formatFullDay, localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Historique du Coach' })

const api = useApi()
// Several are made in the same second: the cursor is the time and the id of the last one.
const { firstPage, items: recommendations, status, error, refresh, hasMore, loading, notice, list, keep, loadMore } = usePagedList<Recommendation>(
  COACH_KEYS.history,
  cursor => api.get('/api/coach/recommendations', { query: { limit: 20, ...cursor } }),
  { size: 20, noun: ['recommandation'] },
)

const onDay = (time: string) => formatFullDay(localDay(new Date(time)))
</script>

<template>
  <UiPage>
    <ShellBackLink
      to="/coach"
      label="Coach"
    />
    <UiLoadError
      v-if="error && !firstPage"
      @retry="refresh()"
    />
    <UiCard v-else-if="recommendations.length">
      <UiAlert
        v-if="loading.message.value"
        tone="error"
      >
        {{ loading.message.value }}
      </UiAlert>
      <ul
        ref="list"
        class="m-0 flex list-none flex-col p-0"
        role="list"
        aria-label="Recommandations, la plus récente d’abord"
        tabindex="-1"
      >
        <li
          v-for="recommendation in recommendations"
          :key="recommendation.id"
          :ref="element => keep(recommendation.id, element)"
          class="flex flex-col gap-1 border-line py-2 not-first:border-t"
          tabindex="-1"
        >
          <span class="text-sm text-muted">
            {{ onDay(recommendation.createdAt) }} · <CoachLevel :level="recommendation.level" />
          </span>
          <span class="font-semibold">{{ recommendation.title }}</span>
          <span
            v-if="recommendation.proposal"
            class="text-sm tabular-nums"
          >{{ proposalSummary(recommendation.proposal) }}</span>
          <span class="text-sm">
            {{ statusLabel(recommendation) }}<template v-if="recommendation.decidedAt && recommendation.status !== 'pending'"> le {{ onDay(recommendation.decidedAt) }}</template>
          </span>
          <details
            v-if="recommendation.why.length"
            class="text-sm"
          >
            <summary class="min-h-tap cursor-pointer content-center font-semibold text-accent-ink">
              Pourquoi&nbsp;?
            </summary>
            <ul class="m-0 flex list-disc flex-col gap-1 ps-5">
              <li
                v-for="line in recommendation.why"
                :key="line"
              >
                {{ line }}
              </li>
            </ul>
          </details>
        </li>
      </ul>
      <UiButton
        v-if="hasMore"
        variant="secondary"
        class="self-start"
        :loading="loading.pending.value"
        @click="loadMore"
      >
        Voir plus
      </UiButton>
    </UiCard>
    <UiCard v-else-if="firstPage">
      <UiEmptyState
        :icon="History"
        title="Aucune recommandation pour l’instant"
        description="Chaque recommandation du Coach et ta décision sont gardées ici, la plus récente d’abord."
      />
    </UiCard>
    <p
      v-else-if="status === 'pending'"
      class="text-muted"
      role="status"
    >
      Chargement…
    </p>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </UiPage>
</template>
