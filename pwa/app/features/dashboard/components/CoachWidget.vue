<script setup lang="ts">
import type { DashboardView } from '../dashboard'
import { CALLS_TO_ACTION, LEVELS } from '~/features/coach/coach'
import { plural } from '~/services/text/plural'
import DashboardWidget from './DashboardWidget.vue'
import { useCoachOfDay } from '~/features/coach/coachOfDay'

/** The Coach's most important recommendation (spec §13); deciding on it happens in the Coach. */
// Every widget is given the view; this one is the same whatever it is.
const props = defineProps<{ today: string, view?: DashboardView }>()

const { coach, status, error, refresh } = useCoachOfDay(() => props.today)
const top = computed(() => coach.value?.recommendations[0] ?? null)
const others = computed(() => Math.max(0, (coach.value?.recommendations.length ?? 0) - 1))
/** What to do about it (spec §130: actionable): a proposal is answered in the Coach, advice where it applies. */
const action = computed(() => {
  const recommendation = top.value
  if (!recommendation) return null
  if (recommendation.proposal) return { label: 'Voir la proposition', to: '/coach' }
  return CALLS_TO_ACTION[recommendation.kind] ?? null
})
</script>

<template>
  <DashboardWidget
    title="Coach"
    to="/coach"
    :loading="status === 'pending' && !coach"
    :failed="Boolean(error) && !coach"
    @retry="refresh()"
  >
    <div
      v-if="top"
      class="flex flex-col gap-1"
    >
      <p class="font-bold">
        <span aria-hidden="true">{{ LEVELS[top.level].emoji }}</span>
        {{ LEVELS[top.level].label }}&nbsp;: {{ top.title }}
      </p>
      <p class="text-sm">
        {{ top.body }}
      </p>
      <UiLinkButton
        v-if="action"
        :to="action.to"
        variant="secondary"
        class="mt-1 self-start"
      >
        {{ action.label }}
      </UiLinkButton>
      <p
        v-if="others"
        class="text-sm text-muted"
      >
        Et {{ others }} {{ plural(others, 'autre') }} {{ plural(others, 'recommandation') }} dans le Coach.
      </p>
    </div>
    <p
      v-else
      class="text-sm text-muted"
    >
      Rien à signaler&nbsp;: continue ton plan.
    </p>
  </DashboardWidget>
</template>
