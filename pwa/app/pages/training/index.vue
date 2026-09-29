<script setup lang="ts">
import { Dumbbell } from 'lucide-vue-next'
import SyncStatus from '~/features/training/components/SyncStatus.vue'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { useWorkoutOutbox } from '~/features/training/outbox'
import { keepActiveProgram, keepPlans, keptActiveProgram, planFor } from '~/features/training/plans'
import { useWorkoutSession } from '~/features/training/session'
import type { Program } from '~/features/training/training'
import type { Workout } from '~/features/training/workout'
import { asApiError } from '~/services/api/client'
import { messageFor } from '~/services/api/messages'

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
const starting = ref<number | 'free' | null>(null)
const startError = ref<string | null>(null)

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
  starting.value = day
  startError.value = null
  try {
    await session.start(day === 'free' || !active.value ? null : await planFor(api, active.value, day))
    await navigateTo('/training/seance')
  }
  catch (caught) {
    const failure = asApiError(caught)
    startError.value = ['network', 'server'].includes(failure.kind) ? 'Pas de connexion, et ce jour n’est pas encore gardé sur l’appareil.' : messageFor(failure)
  }
  finally {
    starting.value = null
  }
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
  <div class="subpage">
    <SyncStatus />
    <UiCard
      v-if="local || elsewhere"
      title="Séance en cours"
    >
      <p class="muted">
        Ta séance est toujours en cours ({{ local?.programDay?.dayName ?? elsewhere?.programDay?.dayName ?? 'séance libre' }}).
      </p>
      <div class="form__actions">
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
        class="muted"
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
        class="days"
      >
        <li
          v-for="(day, index) in active.days"
          :key="day.name"
        >
          <UiButton
            class="days__start"
            :loading="starting === index"
            :disabled="starting !== null && starting !== index"
            @click="start(index)"
          >
            Démarrer {{ day.name }}
          </UiButton>
          <span class="muted">{{ exercisesIn(active, index) }}&nbsp;exercice{{ exercisesIn(active, index) > 1 ? 's' : '' }}</span>
        </li>
      </ul>
      <NuxtLink
        :to="`/training/programmes/${active.id}`"
        class="more"
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
      <NuxtLink
        :to="programs.length ? '/training/programmes' : '/training/programmes/nouveau'"
        class="link-button"
      >
        {{ programs.length ? 'Mes programmes' : 'Créer un programme' }}
      </NuxtLink>
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

<style scoped>
.muted {
  margin-bottom: var(--space-3);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.days {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.days li {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
}

.days .muted {
  margin: 0;
}

.more {
  display: inline-flex;
  align-items: center;
  min-height: var(--tap-target);
  color: var(--color-accent-text);
  font-weight: 600;
}
</style>
