<script setup lang="ts">
import WorkoutClock from './WorkoutClock.vue'

/** The workout's day, where it stands and how long it has lasted, with the way out. */
defineProps<{ title: string, current: number, count: number, startedAt: string }>()
const emit = defineEmits<{ finish: [], long: [] }>()
</script>

<template>
  <header class="flex items-center justify-between gap-3">
    <div>
      <h1
        id="page-title"
        class="text-xl font-extrabold"
      >
        {{ title }}
      </h1>
      <p class="text-muted tabular-nums">
        <template v-if="count">
          <span aria-hidden="true">Exercice {{ current + 1 }}/{{ count }} · </span>
          <span class="sr-only">Exercice {{ current + 1 }} sur {{ count }}, </span>
        </template>
        <WorkoutClock
          :started-at="startedAt"
          @long="emit('long')"
        />
      </p>
    </div>
    <UiButton
      variant="secondary"
      @click="emit('finish')"
    >
      Terminer
    </UiButton>
  </header>
</template>
