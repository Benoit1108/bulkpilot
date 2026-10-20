<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next'
import WeeklyReportCard from '~/features/coach/components/WeeklyReportCard.vue'
import { COACH_KEYS } from '~/features/coach/coach'

definePageMeta({ title: 'Rapports de la semaine' })

/** About a season of weeks at a time; the next page starts before the last week shown. */
const PAGE = 12
const api = useApi()
// This year's weeks go without the year.
const today = useToday()
const { firstPage, items: reports, status, error, refresh, hasMore, loading, notice, list, keep, loadMore } = usePagedList(
  COACH_KEYS.reports,
  async cursor => (await api.get('/api/coach/reports', { query: { limit: PAGE, ...(cursor.before && { before: cursor.before }) } }))
    .map(report => ({ ...report, id: report.weekStart })),
  { size: PAGE, noun: ['rapport'], cursor: last => ({ before: last.weekStart }) },
)
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
    <template v-else-if="reports.length">
      <UiAlert
        v-if="loading.message.value"
        tone="error"
      >
        {{ loading.message.value }}
      </UiAlert>
      <div
        ref="list"
        class="flex flex-col gap-4"
        tabindex="-1"
      >
        <div
          v-for="report in reports"
          :key="report.id"
          :ref="element => keep(report.id, element)"
          tabindex="-1"
        >
          <WeeklyReportCard
            :report="report"
            :today="today"
          />
        </div>
      </div>
      <UiButton
        v-if="hasMore"
        variant="secondary"
        class="self-start"
        :loading="loading.pending.value"
        @click="loadMore"
      >
        Voir plus
      </UiButton>
    </template>
    <UiCard v-else-if="firstPage">
      <UiEmptyState
        :icon="CalendarDays"
        title="Aucun rapport pour l’instant"
        description="Le premier rapport arrive quand une semaine est finie (du lundi au dimanche), à ta prochaine visite du Coach."
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
