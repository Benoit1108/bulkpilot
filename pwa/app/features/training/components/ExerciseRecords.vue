<script setup lang="ts">
import type { components } from '~/types/api'
import type { Measurement } from '~/features/training/training'
import { formatRecord } from '~/features/training/workout'
import StrengthChart from './StrengthChart.vue'

/** An exercise's records, then its last sessions (spec §47). */
const props = defineProps<{ progress: components['schemas']['ExerciseProgress'], measurement: Measurement }>()

const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' })
type Session = (typeof props.progress)['sessions'][number]
function summary(session: Session): string {
  return [
    session.topLoadKg ? `${session.topLoadKg.toLocaleString('fr-FR')}\u00A0kg max` : null,
    session.bestReps ? `${session.bestReps}\u00A0reps` : null,
    session.bestSeconds ? `${session.bestSeconds}\u00A0s` : null,
    `${session.workingSets}\u00A0séries`,
  ].filter(Boolean).join(' · ')
}
const recent = computed(() => [...props.progress.sessions].reverse().slice(0, 5))
const ROW = 'flex flex-wrap justify-between gap-x-3 gap-y-1 border-t border-line py-2'
</script>

<template>
  <UiCard title="Mes records">
    <p
      v-if="!progress.records.length"
      class="text-sm text-muted"
    >
      Pas encore de record&nbsp;: ils apparaîtront après ta première séance avec cet exercice.
    </p>
    <template v-else>
      <ul class="flex flex-col">
        <li
          v-for="record in progress.records"
          :key="record.kind"
          :class="ROW"
        >
          {{ formatRecord(record, measurement) }}
          <span class="text-sm text-muted">{{ dateFormat.format(new Date(record.date)) }}</span>
        </li>
      </ul>
      <StrengthChart
        :progress="progress"
        :measurement="measurement"
      />
      <h3 class="mt-3 font-bold">
        Dernières séances
      </h3>
      <ul class="flex flex-col">
        <li
          v-for="session in recent"
          :key="session.workoutId"
          :class="ROW"
        >
          <NuxtLink
            :to="`/training/seances/${session.workoutId}`"
            class="font-semibold text-accent-ink"
          >
            {{ dateFormat.format(new Date(session.date)) }}
          </NuxtLink>
          <span class="text-sm text-muted">{{ summary(session) }}</span>
        </li>
      </ul>
    </template>
  </UiCard>
</template>
