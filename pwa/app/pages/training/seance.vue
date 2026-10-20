<script setup lang="ts">
import { prepareChime } from '~/features/training/restChime'
import CurrentExercise from '~/features/training/components/CurrentExercise.vue'
import DoneSets from '~/features/training/components/DoneSets.vue'
import FinishWorkout from '~/features/training/components/FinishWorkout.vue'
import RestTimer from '~/features/training/components/RestTimer.vue'
import SetCorrection from '~/features/training/components/SetCorrection.vue'
import SetForm from '~/features/training/components/SetForm.vue'
import SyncStatus from '~/features/training/components/SyncStatus.vue'
import WorkoutHeader from '~/features/training/components/WorkoutHeader.vue'
import WorkoutNavigation from '~/features/training/components/WorkoutNavigation.vue'
import { startingValues } from '~/features/training/nextSet'
import { useWorkoutOutbox } from '~/features/training/outbox'
import { useWorkoutSession, type SetValues } from '~/features/training/session'
import type { Exercise } from '~/features/training/training'
import { useWorkoutRecords } from '~/features/training/workoutRecords'
import { dropSetDue, formatSet, nextKind, unfinished, type WorkoutExercise, type WorkoutSet } from '~/features/training/workout'

definePageMeta({ title: 'Séance', layout: 'focus' })

const route = useRoute()
const session = useWorkoutSession()
const outbox = useWorkoutOutbox()
const workout = session.workout
const notice = ref<string | null>(null)
const current = ref<{ focus: () => void } | null>(null)
const restTimer = ref<{ focus: () => void } | null>(null)
const correction = ref<{ focus: () => void } | null>(null)
const doneSets = ref<{ focus: (id: string) => void } | null>(null)
const { keepScreenOn } = useWakeLock()
const { refresh } = useWorkoutRecords(notice)

let unmounted = false
onMounted(async () => {
  await session.load()
  if (unmounted) return
  if (!workout.value) {
    await navigateTo('/training')
    return
  }
  finishing.value = route.query.terminer === '1'
  keepScreenOn()
  void refresh()
})

const stopListening = outbox.onSent((write) => {
  if (write.workoutId === workout.value?.id && write.op !== 'finish' && write.op !== 'discard') void refresh()
})

onBeforeUnmount(() => {
  unmounted = true
  stopListening()
})

async function hourReached(): Promise<void> {
  if (!workout.value || workout.value.hourAlerted) return
  await session.markHourAlerted()
  navigator.vibrate?.([300, 150, 300])
  notice.value = '1\u00A0h d’entraînement\u00A0: pense à ta récupération.'
}

const exercise = computed(() => workout.value?.exercises[workout.value.current] ?? null)
const sets = computed(() => workout.value?.sets ?? [])
const setsOfExercise = computed(() => (exercise.value ? sets.value.filter(set => set.exerciseIndex === exercise.value!.index) : []))
const kind = computed(() => (exercise.value ? nextKind(exercise.value, sets.value) : 'normal'))
const left = computed(() => (workout.value ? unfinished(workout.value.exercises, sets.value) : []))
const allDone = computed(() => Boolean(workout.value?.exercises.length) && left.value.length === 0 && !(exercise.value && dropSetDue(exercise.value, sets.value)))
const anotherSet = ref(false)
const initial = computed<Partial<SetValues>>(() => (exercise.value ? startingValues(exercise.value, setsOfExercise.value, kind.value) : {}))
/** A new form for each set, so its values start again from the right place. */
const formKey = computed(() => `${workout.value?.current}-${sets.value.length}`)

function focusExercise(): void {
  void nextTick(() => current.value?.focus())
}

async function record(values: SetValues): Promise<void> {
  // The rest starts with this set: the sound that ends it is unlocked by this very tap (iOS).
  prepareChime()
  const before = workout.value?.current
  const set = await session.recordSet(values)
  const now = workout.value
  if (!set || !now) return
  const moved = now.current !== before ? ` Suivant\u00A0: ${now.exercises[now.current]?.name}.` : ''
  notice.value = `Série enregistrée\u00A0: ${formatSet(set, now.exercises[set.exerciseIndex]!.measurement)}.${moved}`
  anotherSet.value = false
  await nextTick()
  if (now.rest) restTimer.value?.focus()
  else focusExercise()
}

/** A correction happens on the set's own exercise, whatever is on screen. */
const editing = ref<WorkoutSet | null>(null)
const editingExercise = computed<WorkoutExercise | null>(() => (editing.value ? workout.value?.exercises[editing.value.exerciseIndex] ?? null : null))
const editingNumber = computed(() => (editing.value ? setsOfExercise.value.findIndex(set => set.id === editing.value!.id) + 1 : 0))
watch(() => workout.value?.current, () => (editing.value = null))

async function startCorrecting(set: WorkoutSet): Promise<void> {
  editing.value = set
  await nextTick()
  correction.value?.focus()
}

async function correct(values: SetValues): Promise<void> {
  const set = editing.value
  if (!set) return
  await session.correctSet(set.id, values)
  editing.value = null
  notice.value = 'Série corrigée.'
  await nextTick()
  doneSets.value?.focus(set.id)
}

async function cancelCorrecting(): Promise<void> {
  const id = editing.value?.id
  editing.value = null
  await nextTick()
  if (id) doneSets.value?.focus(id)
}

async function remove(set: WorkoutSet): Promise<void> {
  if (editing.value?.id === set.id) editing.value = null
  await session.removeSet(set.id)
  notice.value = 'Série supprimée.'
  focusExercise()
}

async function goTo(index: number): Promise<void> {
  const all = workout.value?.exercises ?? []
  if (!all[index]) return
  await session.goTo(index)
  notice.value = `Exercice ${index + 1} sur ${all.length}\u00A0: ${all[index].name}.`
  focusExercise()
}

async function restOver(announce: boolean): Promise<void> {
  await session.endRest()
  if (announce) notice.value = `Repos terminé${exercise.value ? `, c’est reparti\u00A0: ${exercise.value.name}` : ''}.`
  focusExercise()
}

async function add(chosen: Exercise): Promise<void> {
  await session.addExercise(chosen)
  notice.value = `${chosen.name} ajouté à la séance.`
  focusExercise()
}

const finishing = ref(false)
async function finish(): Promise<void> {
  const id = await session.finish()
  if (id) await navigateTo(`/training/seances/${id}`)
}
async function discard(): Promise<void> {
  await session.discard()
  await navigateTo('/training')
}
</script>

<template>
  <div
    v-if="workout"
    class="flex flex-col gap-4"
  >
    <WorkoutHeader
      :title="workout.programDay?.dayName ?? 'Séance libre'"
      :current="workout.current"
      :count="workout.exercises.length"
      :started-at="workout.startedAt"
      @finish="finishing = true"
      @long="hourReached"
    />

    <SyncStatus />

    <RestTimer
      v-if="workout.rest"
      ref="restTimer"
      :rest="workout.rest"
      @pause="session.pauseRest()"
      @resume="session.resumeRest()"
      @adjust="session.adjustRest($event)"
      @skip="restOver(false)"
      @done="restOver(true)"
    />

    <section
      v-if="exercise"
      class="flex flex-col gap-3"
      aria-labelledby="exercise-title"
    >
      <CurrentExercise
        ref="current"
        :exercise="exercise"
        :exercises="workout.exercises"
        :own="setsOfExercise"
        :kind="kind"
      />

      <SetCorrection
        v-if="editing && editingExercise"
        ref="correction"
        :exercise="editingExercise"
        :set="editing"
        :number="editingNumber"
        @submit="correct"
        @cancel="cancelCorrecting"
      />
      <UiCard v-else-if="allDone && !anotherSet">
        <p class="mb-3 font-bold">
          Toutes les séries prévues sont faites.
        </p>
        <div class="flex flex-wrap gap-2">
          <UiButton @click="finishing = true">
            Terminer la séance
          </UiButton>
          <UiButton
            variant="secondary"
            @click="anotherSet = true"
          >
            Ajouter une série
          </UiButton>
        </div>
      </UiCard>
      <SetForm
        v-else
        :key="formKey"
        :exercise="exercise"
        :initial="initial"
        submit-label="Valider la série"
        @submit="record"
      />

      <DoneSets
        v-if="setsOfExercise.length"
        ref="doneSets"
        :exercise="exercise"
        :sets="setsOfExercise"
        :editing="editing?.id ?? null"
        @correct="startCorrecting"
        @remove="remove"
      />
    </section>
    <UiCard v-else>
      <p>Séance libre&nbsp;: ajoute le premier exercice.</p>
    </UiCard>

    <WorkoutNavigation
      :exercises="workout.exercises"
      :sets="workout.sets"
      :current="workout.current"
      @go="goTo"
      @add="add"
    />

    <UiDrawer
      v-model:open="finishing"
      title="Terminer la séance"
    >
      <FinishWorkout
        :left="left"
        @finish="finish"
        @resume="finishing = false"
        @discard="discard"
      />
    </UiDrawer>

    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
  <p v-else>
    <span
      id="page-title"
      class="text-xl font-extrabold"
    >Chargement de la séance…</span>
  </p>
</template>
