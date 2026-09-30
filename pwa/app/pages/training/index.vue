<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import { Dumbbell } from 'lucide-vue-next'
import SyncStatus from '~/features/training/components/SyncStatus.vue'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { useWorkoutOutbox } from '~/features/training/outbox'
import { keepActiveProgram, keepPlans, keptActiveProgram } from '~/features/training/plans'
import { useStartWorkout } from '~/features/training/startWorkout'
import { useWorkoutSession } from '~/features/training/session'
import type { Program } from '~/features/training/training'
import type { Workout } from '~/features/training/workout'
import { asApiError } from '~/services/api/client'

definePageMeta({ title: 'Training' })

const api = useApi()
const session = useWorkoutSession()
const outbox = useWorkoutOutbox()
const { data: programs, error, refresh } = useAsyncData(TRAINING_KEYS.programs, () => api.get('/api/programs'))
/** Opened without signal: the program followed as the device kept it (spec §116). */
const kept = ref<Program | null>(null)
const offline = computed(() => !programs.value && error.value !== null && error.value !== undefined && ['network', 'server'].includes(asApiError(error.value).kind))
const active = computed(() => programs.value?.find(program => program.active) ?? (offline.value ? kept.value : null))
const local = session.workout
/** A workout the API has in progress but this device does not (another device, cleared data). */
const elsewhere = ref<Workout | null>(null)
const { start: startWorkout, starting, message: startError } = useStartWorkout()

onMounted(async () => {
  await session.load()
  kept.value = await keptActiveProgram()
  if (local.value) return
  try {
    const current = (await api.get('/api/workouts/in-progress'))[0] ?? null
    // Finished or discarded here, and not sent yet: it is over.
    elsewhere.value = current && !(await outbox.isEnding(current.id)) ? current : null
  }
  catch {
    elsewhere.value = null
  }
})

// The program followed and the plan of each of its days are kept, to start without signal.
watch(programs, (all) => {
  if (!all) return
  const followed = all.find(program => program.active) ?? null
  if (followed) void keepPlans(api, followed)
  else void keepActiveProgram(null)
}, { immediate: true })

async function start(day: number | 'free'): Promise<void> {
  await startWorkout(active.value, day)
}

/** Takes over the workout of another device first, then opens it (to go on, or to finish it). */
async function open(finishing: boolean): Promise<void> {
  if (!local.value && elsewhere.value) await session.resume(elsewhere.value)
  await navigateTo(finishing ? { path: '/training/seance', query: { terminer: '1' } } : '/training/seance')
}

const exercisesIn = (program: Program, day: number) => (program.days[day]?.blocks ?? []).reduce((count, block) => count + block.exercises.length, 0)

const shortcuts = [
  { to: '/training/seances', title: 'Historique', summary: 'Séances passées, séries, records' },
  { to: '/training/programmes', title: 'Programmes', summary: 'Séances types, supersets, versions' },
  { to: '/training/exercices', title: 'Exercices', summary: 'Bibliothèque, exercices perso, consignes, records' },
] as const
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <SyncStatus />
    <UiCard
      v-if="local || elsewhere"
      title="Séance en cours"
    >
      <p class="mb-3 text-sm text-muted">
        Ta séance est toujours en cours ({{ local?.programDay?.dayName ?? elsewhere?.programDay?.dayName ?? 'séance libre' }}).
      </p>
      <div class="flex flex-wrap gap-2">
        <UiButton @click="open(false)">
          Reprendre
        </UiButton>
        <UiButton
          variant="secondary"
          @click="open(true)"
        >
          Terminer
        </UiButton>
      </div>
    </UiCard>

    <UiLoadError
      v-if="error && !programs && !active"
      @retry="refresh()"
    />
    <UiCard
      v-else-if="active"
      :title="active.name"
    >
      <p
        v-if="offline"
        class="mb-3 text-sm text-muted"
      >
        Hors ligne&nbsp;: le programme tel que gardé sur le téléphone.
      </p>
      <UiAlert
        v-if="startError"
        tone="error"
      >
        {{ startError }}
      </UiAlert>
      <ul
        v-if="!local && !elsewhere"
        class="mb-3 flex flex-col gap-2"
      >
        <li
          v-for="(day, index) in active.days"
          :key="day.name"
          class="flex flex-wrap items-center gap-x-3 gap-y-2"
        >
          <UiButton
            :loading="starting === index"
            :disabled="starting !== null && starting !== index"
            @click="start(index)"
          >
            Démarrer {{ day.name }}
          </UiButton>
          <span class="text-sm text-muted">{{ exercisesIn(active, index) }}&nbsp;exercice{{ exercisesIn(active, index) > 1 ? 's' : '' }}</span>
        </li>
      </ul>
      <NuxtLink
        :to="`/training/programmes/${active.id}`"
        :class="TEXT_LINK"
      >
        Voir le programme
      </NuxtLink>
    </UiCard>
    <UiCard v-else-if="programs">
      <UiEmptyState
        :icon="Dumbbell"
        title="Aucun programme suivi"
        :description="programs.length ? 'Choisis le programme à suivre parmi les tiens.' : 'Compose tes séances à partir de la bibliothèque d’exercices.'"
      />
      <UiLinkButton
        :to="programs.length ? '/training/programmes' : '/training/programmes/nouveau'"
      >
        {{ programs.length ? 'Mes programmes' : 'Créer un programme' }}
      </UiLinkButton>
    </UiCard>
    <UiButton
      v-if="!local && !elsewhere"
      variant="secondary"
      :loading="starting === 'free'"
      :disabled="starting !== null && starting !== 'free'"
      @click="start('free')"
    >
      Séance libre
    </UiButton>
    <UiNavList
      label="Entraînement"
      :items="shortcuts"
    />
  </div>
</template>
