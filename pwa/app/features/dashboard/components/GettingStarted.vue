<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import { useNextWorkout, useWeightGoal, useWeightSummary } from '../data'

/**
 * The first steps (spec §81: start fast, go deeper after), each gone once done. Read from what the
 * widgets read (the same keys): a step whose data failed to load is left out rather than guessed.
 */
const props = defineProps<{ today: string }>()
const emit = defineEmits<{ steps: [left: number, total: number] }>()

const { journal } = useNutritionDay(toRef(props, 'today'))
const { data: weight } = useWeightSummary(() => '7d', () => props.today)
const { data: next } = useNextWorkout()
const { data: goal } = useWeightGoal(() => props.today)

const STEPS = [
  { key: 'target', label: 'Fixer ton objectif calorique', to: '/nutrition/objectifs' },
  { key: 'weighIn', label: 'Enregistrer ta première pesée', to: '/progression?ajouter=pesee' },
  { key: 'program', label: 'Choisir un programme d’entraînement', to: '/training/programmes' },
  { key: 'goal', label: 'Fixer un objectif de poids', to: '/progression/objectif' },
] as const

/** Each step: done, to do, or not known (still loading, or failed). */
const missing = computed<Record<(typeof STEPS)[number]['key'], boolean | null>>(() => ({
  target: journal.value ? journal.value.target === null : null,
  weighIn: weight.value ? weight.value.first === null : null,
  program: next.value === undefined ? null : next.value === null,
  goal: goal.value === undefined ? null : goal.value === null,
}))
const left = computed(() => STEPS.filter(step => missing.value[step.key] === true))
watchEffect(() => emit('steps', left.value.length, STEPS.length))
</script>

<template>
  <UiCard
    v-if="left.length"
    title="Pour commencer"
  >
    <p class="text-sm text-muted">
      Quelques réglages, et le tableau de bord se remplit de tes vraies données.
    </p>
    <ol
      class="m-0 flex flex-col gap-1 ps-5"
      role="list"
    >
      <li
        v-for="step in left"
        :key="step.key"
      >
        <NuxtLink
          :to="step.to"
          :class="TEXT_LINK"
        >
          {{ step.label }}
        </NuxtLink>
      </li>
    </ol>
  </UiCard>
</template>
