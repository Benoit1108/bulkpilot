<script setup lang="ts">
import { formatFullDay } from '~/services/calendar/days'
import { formatEstimate, formatKg, formatSignedKg } from '../format'
import type { WeightGoal } from '../progression'

/**
 * The goal followed (spec §52–53): the way done, each milestone and when it was reached, what
 * is left to the next one and to the target, and how long at the current pace (an estimate).
 */
const props = defineProps<{ goal: WeightGoal }>()

const verb = computed(() => (props.goal.direction === 'gaining' ? 'prendre' : 'perdre'))
const left = (kg: number) => `${formatKg(Math.abs(kg))} à ${verb.value}`
const since = computed(() => (props.goal.steps.some(step => step.reachedOn) ? 'le dernier jalon atteint' : 'le départ'))
const stepText = (step: WeightGoal['steps'][number]) => (step.reachedOn ? `atteint le ${formatFullDay(step.reachedOn)}` : 'pas encore')
</script>

<template>
  <div class="flex flex-col gap-4">
    <p>
      De {{ formatKg(goal.startWeightKg) }} (le {{ formatFullDay(goal.startDay) }}) à
      <strong>{{ formatKg(goal.targetKg) }}</strong>.
    </p>
    <UiProgressBar
      label="Chemin parcouru vers l’objectif"
      :value="goal.percent"
      :max="100"
      :value-text="`${goal.percent}&nbsp;%`"
      size="lg"
    />
    <p class="text-sm text-muted">
      {{ goal.percent }}&nbsp;% du chemin · {{ formatSignedKg(goal.sinceLastStepKg) }} depuis {{ since }}
    </p>
    <ol class="m-0 flex list-none flex-col gap-1 p-0">
      <li
        v-for="step in goal.steps"
        :key="step.weightKg"
        class="flex flex-wrap items-baseline justify-between gap-x-3 border-t border-line py-2"
      >
        <span :class="step.target ? 'font-bold' : 'font-semibold'">
          {{ step.target ? 'Objectif' : 'Jalon' }} {{ formatKg(step.weightKg) }}
        </span>
        <span
          class="text-sm"
          :class="step.reachedOn ? 'text-success' : 'text-muted'"
        >{{ stepText(step) }}</span>
      </li>
    </ol>
    <dl class="m-0 flex flex-col gap-2">
      <div v-if="goal.next && !goal.next.target">
        <dt class="text-sm text-muted">
          Prochain jalon ({{ formatKg(goal.next.weightKg) }})
        </dt>
        <dd class="m-0">
          <strong>{{ left(goal.next.remainingKg) }}</strong> — {{ formatEstimate(goal.next.estimate) }}
        </dd>
      </div>
      <div>
        <dt class="text-sm text-muted">
          Objectif ({{ formatKg(goal.targetKg) }})
        </dt>
        <dd class="m-0">
          <template v-if="goal.toTarget.status === 'reached'">
            <strong>Atteint</strong>
          </template>
          <template v-else>
            <strong>{{ left(goal.toTargetKg) }}</strong> — {{ formatEstimate(goal.toTarget) }}
          </template>
        </dd>
      </div>
    </dl>
  </div>
</template>
