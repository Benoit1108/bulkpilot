<script setup lang="ts">
import { STRATEGIES, blockLabel, labelOf, prescription, type ProgramDay, type ProgramExercise } from '~/features/training/training'

/** A program version, day by day, as it will be followed. */
defineProps<{ days: ProgramDay[] }>()

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
  <div class="summary">
    <div
      v-for="day in days"
      :key="day.name"
      class="day"
    >
      <h3 class="day__name">
        {{ day.name }}
      </h3>
      <ol class="day__blocks">
        <li
          v-for="(block, b) in day.blocks"
          :key="b"
          class="block"
          :class="{ 'block--superset': block.exercises.length > 1 }"
        >
          <p
            v-if="block.exercises.length > 1"
            class="block__kind"
          >
            Superset
          </p>
          <ul class="block__exercises">
            <li
              v-for="(exercise, e) in block.exercises"
              :key="e"
              class="exercise"
            >
              <span
                class="exercise__label"
                aria-hidden="true"
              >{{ blockLabel(b, e, block.exercises.length) }}</span>
              <span class="exercise__text">
                <span class="exercise__name">
                  {{ exercise.name }}<template v-if="!exercise.available">
                    (supprimé)
                  </template>
                </span>
                <span class="exercise__detail">{{ prescription(exercise) }}</span>
                <span class="exercise__detail">{{ extras(exercise) }}</span>
                <span
                  v-if="exercise.notes"
                  class="exercise__notes"
                >{{ exercise.notes }}</span>
              </span>
            </li>
          </ul>
        </li>
      </ol>
    </div>
  </div>
</template>

<style scoped>
.summary {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.day__name {
  margin-bottom: var(--space-2);
  font-weight: 700;
}

.day__blocks {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.block--superset {
  padding-left: var(--space-2);
  border-left: 3px solid var(--color-accent);
}

.block__kind {
  color: var(--color-text-muted);
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.block__exercises {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.exercise {
  display: grid;
  grid-template-columns: 2rem minmax(0, 1fr);
  gap: var(--space-2);
}

.exercise__label {
  color: var(--color-text-muted);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.exercise__text {
  display: flex;
  flex-direction: column;
}

.exercise__name {
  font-weight: 600;
}

.exercise__detail,
.exercise__notes {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.exercise__notes {
  font-style: italic;
}
</style>
