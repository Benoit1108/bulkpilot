<script setup lang="ts">
import { STRATEGIES, blockLabel, labelOf, prescription, type ProgramDay, type ProgramExercise } from '~/features/training/training'

/** A program version, day by day, as it will be followed. */
defineProps<{ days: ProgramDay[] }>()

const isSuperset = (block: ProgramDay['blocks'][number]) => block.exercises.length > 1
const dayId = useId()

function extras(exercise: ProgramExercise): string {
  return [
    exercise.warmupSets ? `${exercise.warmupSets} d’échauffement` : null,
    exercise.dropSet ? 'drop set sur la dernière' : null,
    exercise.tempo ? `tempo ${exercise.tempo}` : null,
    labelOf(STRATEGIES, exercise.strategy),
  ].filter(Boolean).join(' · ')
}
</script>

<template>
  <!-- Side by side on a wide screen: a push / pull / legs week reads at a glance. -->
  <div class="grid grid-cols-[repeat(auto-fit,minmax(16rem,1fr))] gap-4">
    <div
      v-for="(day, d) in days"
      :key="day.name"
      role="group"
      :aria-labelledby="`${dayId}-${d}`"
    >
      <h3
        :id="`${dayId}-${d}`"
        class="mb-2 font-bold"
      >
        {{ day.name }}
      </h3>
      <ol
        class="flex flex-col gap-2"
        role="list"
      >
        <li
          v-for="(block, b) in day.blocks"
          :key="b"
          :class="{ 'border-l-3 border-accent pl-2': isSuperset(block) }"
        >
          <p
            v-if="isSuperset(block)"
            class="text-sm font-semibold text-muted"
          >
            Superset
          </p>
          <ul
            class="flex flex-col gap-2"
            role="list"
          >
            <li
              v-for="(exercise, e) in block.exercises"
              :key="e"
              class="grid grid-cols-[2rem_minmax(0,1fr)] gap-2"
            >
              <span
                class="font-bold text-muted tabular-nums"
                aria-hidden="true"
                data-testid="exercise-label"
              >{{ blockLabel(b, e, block.exercises.length) }}</span>
              <span class="flex flex-col">
                <span class="font-semibold">
                  {{ exercise.name }}<template v-if="!exercise.available">
                    (supprimé)
                  </template>
                </span>
                <span class="text-sm text-muted">{{ prescription(exercise) }}</span>
                <span class="text-sm text-muted">{{ extras(exercise) }}</span>
                <span
                  v-if="exercise.notes"
                  class="text-sm text-muted italic"
                >{{ exercise.notes }}</span>
              </span>
            </li>
          </ul>
        </li>
      </ol>
    </div>
  </div>
</template>
