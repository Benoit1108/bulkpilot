<script setup lang="ts">
import { CalendarDays } from 'lucide-vue-next'
import WeeklyReportCard from '~/features/coach/components/WeeklyReportCard.vue'
import { COACH_KEYS } from '~/features/coach/coach'

definePageMeta({ title: 'Rapports de la semaine' })

const api = useApi()
// This year's weeks go without the year.
const today = useToday()
const { data: reports, status, error, refresh } = useAsyncData(COACH_KEYS.reports, () => api.get('/api/coach/reports'))
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/coach"
      label="Coach"
    />
    <UiLoadError
      v-if="error && !reports"
      @retry="refresh()"
    />
    <template v-else-if="reports?.length">
      <WeeklyReportCard
        v-for="report in reports"
        :key="report.weekStart"
        :report="report"
        :today="today"
      />
    </template>
    <UiCard v-else-if="reports">
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
  </div>
</template>
