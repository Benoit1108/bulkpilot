<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import { VERDICTS, type WeeklyReport } from '../coach'
import { reportAdherence, reportNutrition, reportTraining, reportWeight, weekTitle } from '../format'

/**
 * The report of a finished week (spec §59): weight, eating, training, the Coach's verdict and the
 * action it asks for.
 */
defineProps<{ report: WeeklyReport, today: string, linkToAll?: boolean }>()
</script>

<template>
  <UiCard
    :title="weekTitle(report.weekStart, today)"
    as="article"
  >
    <template
      v-if="linkToAll"
      #actions
    >
      <NuxtLink
        to="/coach/rapports"
        :class="TEXT_LINK"
        class="text-sm"
      >
        Tous les rapports
      </NuxtLink>
    </template>
    <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
      <dt class="text-muted">
        Poids
      </dt>
      <dd class="m-0 tabular-nums">
        {{ reportWeight(report) }}
      </dd>
      <dt class="text-muted">
        Nutrition
      </dt>
      <dd class="m-0 tabular-nums">
        {{ reportNutrition(report) }}
      </dd>
      <dt class="text-muted">
        Training
      </dt>
      <dd class="m-0 tabular-nums">
        {{ reportTraining(report) }}
      </dd>
      <dt class="text-muted">
        Coach
      </dt>
      <dd class="m-0 font-semibold">
        {{ VERDICTS[report.verdict] }}
      </dd>
      <dt class="text-muted">
        Action
      </dt>
      <dd class="m-0">
        {{ report.action ?? 'Continuer le plan actuel' }}
      </dd>
      <dt class="text-muted">
        Adhérence
      </dt>
      <dd class="m-0 tabular-nums">
        {{ reportAdherence(report) }}
      </dd>
    </dl>
  </UiCard>
</template>
