<script setup lang="ts">
import { History } from 'lucide-vue-next'
import { COACH_KEYS, LEVELS, statusLabel, type Recommendation } from '~/features/coach/coach'
import { proposalSummary } from '~/features/coach/format'
import { formatFullDay, localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Historique du Coach' })

const PAGE = 20
const api = useApi()
const { data: firstPage, status, error, refresh } = useAsyncData(COACH_KEYS.history, () => api.get('/api/coach/recommendations', { query: { limit: PAGE } }))
const more = ref<Recommendation[]>([])
const recommendations = computed(() => [...(firstPage.value ?? []), ...more.value])
const hasMore = ref(true)
// A page asked for before the list was read again belongs to the old list: it is dropped.
let listVersion = 0
watch(firstPage, (page) => {
  listVersion++
  more.value = []
  hasMore.value = (page?.length ?? 0) === PAGE
}, { immediate: true })

const loading = useSubmission()
const notice = ref<string | null>(null)
// By id: the order of a v-for ref array is not the list's.
const items = new Map<string, HTMLElement>()
function keep(id: string, element: unknown): void {
  if (element instanceof HTMLElement) items.set(id, element)
  else items.delete(id)
}
async function loadMore(): Promise<void> {
  const last = recommendations.value.at(-1)
  const version = listVersion
  let added: Recommendation[] = []
  await loading.run(async () => {
    // Several are made in the same second: the cursor is the time and the id of the last one.
    const page = await api.get('/api/coach/recommendations', { query: { limit: PAGE, before: last?.createdAt, beforeId: last?.id } })
    if (version !== listVersion) return
    more.value.push(...page)
    hasMore.value = page.length === PAGE
    added = page
  })
  const first = added[0]
  if (!first) return
  notice.value = `${added.length} recommandation${added.length > 1 ? 's' : ''} de plus.`
  await nextTick()
  // The button may be gone (last page): focus goes to the first one added.
  items.get(first.id)?.focus()
}

const onDay = (time: string) => formatFullDay(localDay(new Date(time)))
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
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
      <ul class="m-0 flex list-none flex-col p-0">
        <li
          v-for="recommendation in recommendations"
          :key="recommendation.id"
          :ref="element => keep(recommendation.id, element)"
          class="flex flex-col gap-1 border-line py-2 not-first:border-t"
          tabindex="-1"
        >
          <span class="text-sm text-muted">
            {{ onDay(recommendation.createdAt) }} · <span aria-hidden="true">{{ LEVELS[recommendation.level].emoji }}</span> {{ LEVELS[recommendation.level].label }}
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
  </div>
</template>
