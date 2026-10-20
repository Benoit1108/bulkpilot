<script setup lang="ts">
import { Dumbbell } from 'lucide-vue-next'
import { useWorkoutSession } from '~/features/training/session'
import { useStartWorkout } from '~/features/training/startWorkout'
import { formatDayMonth, localDay } from '~/services/calendar/days'
import { plural } from '~/services/text/plural'
import { useNextWorkout } from '../data'
import DashboardWidget from './DashboardWidget.vue'

/** The next workout and a button to start it (spec §13); the server says which day comes next. */
const props = defineProps<{ today: string }>()

const session = useWorkoutSession()
const { data: next, status, error, refresh } = useNextWorkout()
// A new day: the last session may have been yesterday's (the key does not change with the day).
watch(() => props.today, () => void refresh())

onMounted(() => session.load())
const inProgress = computed(() => Boolean(session.workout.value) || Boolean(next.value?.inProgress))

const { start: startWorkout, pending: starting, message: startError } = useStartWorkout()
async function start(): Promise<void> {
  const workout = next.value
  if (workout) await startWorkout({ id: workout.programId, currentVersion: workout.versionNumber }, workout.dayIndex)
}

const lastDone = computed(() => (next.value?.lastDoneAt ? formatDayMonth(localDay(new Date(next.value.lastDoneAt)), props.today) : null))
</script>

<template>
  <DashboardWidget
    title="Entraînement"
    to="/training"
    :loading="status === 'pending' && next === undefined"
    :failed="Boolean(error) && next === undefined"
    @retry="refresh()"
  >
    <div
      v-if="inProgress"
      class="flex flex-col gap-2"
    >
      <p>Ta séance est en cours.</p>
      <UiLinkButton :to="session.workout.value ? '/training/seance' : '/training'">
        Reprendre la séance
      </UiLinkButton>
    </div>
    <div
      v-else-if="next"
      class="flex flex-col gap-3"
    >
      <!-- On a phone the button takes the whole width under the day, within the thumb's reach. -->
      <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-3 sm:grid-cols-[auto_minmax(0,1fr)_auto]">
        <span class="grid size-tap place-items-center rounded-md bg-surface-raised text-accent">
          <Dumbbell
            :size="22"
            aria-hidden="true"
          />
        </span>
        <p>
          <span class="block text-lg font-bold">{{ next.dayName }}</span>
          <span class="block text-sm text-muted">
            {{ next.programName }} · {{ next.exerciseCount }} {{ plural(next.exerciseCount, 'exercice') }}<template v-if="lastDone"> · fait la dernière fois le {{ lastDone }}</template>
          </span>
        </p>
        <UiButton
          class="col-span-2 sm:col-span-1"
          :loading="starting"
          :aria-label="`Démarrer ${next.dayName}`"
          @click="start"
        >
          Démarrer
        </UiButton>
      </div>
      <UiAlert
        v-if="startError"
        tone="error"
      >
        {{ startError }}
      </UiAlert>
    </div>
    <div
      v-else
      class="flex flex-col gap-2"
    >
      <p class="text-sm text-muted">
        Aucun programme suivi&nbsp;: choisis-en un pour savoir quelle séance vient ensuite.
      </p>
      <UiLinkButton
        to="/training/programmes"
        variant="secondary"
      >
        Choisir un programme
      </UiLinkButton>
    </div>
  </DashboardWidget>
</template>
