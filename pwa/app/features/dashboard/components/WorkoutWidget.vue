<script setup lang="ts">
import type { DashboardView } from '../dashboard'
import NextWorkoutWidget from './NextWorkoutWidget.vue'
import SessionsWidget from './SessionsWidget.vue'

/**
 * Training (spec §13): for the day, the next workout and a button to start it; over a week or a
 * month, the sessions done. Each view reads only what it shows.
 */
defineProps<{ view: DashboardView, today: string }>()
</script>

<template>
  <NextWorkoutWidget
    v-if="view === 'day'"
    :today="today"
  />
  <SessionsWidget
    v-else
    :view="view"
    :today="today"
  />
</template>
