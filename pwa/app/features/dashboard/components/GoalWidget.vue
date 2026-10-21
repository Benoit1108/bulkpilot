<script setup lang="ts">
import type { DashboardView } from '../dashboard'
import { formatEstimate, formatKg } from '~/features/progression/format'
import { useWeightGoal } from '../data'

/** The weight goal followed (spec §13 « progression »): the way done and what is left. */
// Every widget is given the view; this one is the same whatever it is.
const props = defineProps<{ today: string, view?: DashboardView }>()

// The same key and answer as the goal page: no goal is null, not an error.
const { data: goal, status, error, refresh } = useWeightGoal(() => props.today)
// A new day: the estimate moves with it.
watch(() => props.today, () => void refresh())
const verb = computed(() => (goal.value?.direction === 'gaining' ? 'prendre' : 'perdre'))
</script>

<template>
  <UiTile
    title="Objectif de poids"
    to="/progression/objectif"
    :loading="status === 'pending' && goal === undefined"
    :failed="Boolean(error) && !goal"
    @retry="refresh()"
  >
    <template v-if="goal">
      <p class="text-sm">
        <span class="block text-2xl font-bold tabular-nums">{{ goal.percent }}&nbsp;%</span> du chemin vers {{ formatKg(goal.targetKg) }}
      </p>
      <UiProgressBar
        :label="`Chemin parcouru vers ${formatKg(goal.targetKg)}`"
        :value="goal.percent"
        :max="100"
        :value-text="`${goal.percent}&nbsp;%`"
      />
      <p
        v-if="goal.toTarget.status !== 'reached'"
        class="text-xs text-muted"
      >
        {{ formatKg(Math.abs(goal.toTargetKg)) }} à {{ verb }}, {{ formatEstimate(goal.toTarget) }}
      </p>
      <p
        v-else
        class="text-sm text-success"
      >
        Objectif atteint&nbsp;!
      </p>
    </template>
    <div
      v-else
      class="flex flex-col gap-2"
    >
      <p class="text-sm text-muted">
        Pas d’objectif de poids pour l’instant.
      </p>
      <UiLinkButton
        to="/progression/objectif"
        variant="secondary"
      >
        Fixer un objectif
      </UiLinkButton>
    </div>
  </UiTile>
</template>
