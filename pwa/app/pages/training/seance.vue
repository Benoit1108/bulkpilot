<script setup lang="ts">
import { ListOrdered } from 'lucide-vue-next'
import DoneSets from '~/features/training/components/DoneSets.vue'
import ExercisePicker from '~/features/training/components/ExercisePicker.vue'
import FinishWorkout from '~/features/training/components/FinishWorkout.vue'
import RestTimer from '~/features/training/components/RestTimer.vue'
import SetForm from '~/features/training/components/SetForm.vue'
import SyncStatus from '~/features/training/components/SyncStatus.vue'
import WorkoutClock from '~/features/training/components/WorkoutClock.vue'
import { useWorkoutOutbox } from '~/features/training/outbox'
import { useWorkoutSession, type SetValues } from '~/features/training/session'
import { blockLetter, formatRest, formatTarget, labelOf, prescription, type Exercise } from '~/features/training/training'
import { dropSetDue, formatRecord, formatSet, formatSuggestion, isWorking, nextKind, SET_KINDS, unfinished, workingSetsOf, type WorkoutExercise, type WorkoutSet } from '~/features/training/workout'

definePageMeta({ title: 'Séance', layout: 'focus' })

const api = useApi()
const route = useRoute()
const session = useWorkoutSession()
const outbox = useWorkoutOutbox()
const workout = session.workout
const notice = ref<string | null>(null)
const heading = ref<HTMLElement | null>(null)
const restTimer = ref<{ focus: () => void } | null>(null)
const setForm = ref<{ focus: () => void } | null>(null)
const doneSets = ref<{ focus: (id: string) => void } | null>(null)

// The screen stays on while training, where the browser allows it; browsers release the lock
// whenever the page is hidden, so it is asked for again on return.
type Sentinel = { release: () => Promise<void> }
let wakeLock: Sentinel | null = null
let unmounted = false
async function keepScreenOn(): Promise<void> {
  if (document.visibilityState !== 'visible' || unmounted) return
  try {
    const lock = await (navigator as Navigator & { wakeLock?: { request: (type: 'screen') => Promise<Sentinel> } }).wakeLock?.request('screen') ?? null
    if (unmounted) void lock?.release()
    else wakeLock = lock
  }
  catch {
    wakeLock = null
  }
}
const onVisibility = () => void keepScreenOn()

onMounted(async () => {
  await session.load()
  if (unmounted) return
  if (!workout.value) {
    await navigateTo('/training')
    return
  }
  finishing.value = route.query.terminer === '1'
  document.addEventListener('visibilitychange', onVisibility)
  void keepScreenOn()
  void refresh()
})

const stopListening = outbox.onSent((write) => {
  if (write.workoutId === workout.value?.id && write.op !== 'finish' && write.op !== 'discard') void refresh()
})

onBeforeUnmount(() => {
  unmounted = true
  document.removeEventListener('visibilitychange', onVisibility)
  stopListening()
  void wakeLock?.release().catch(() => {})
})

/**
 * What only the API knows (records, suggestions), asked again after each write it received.
 * One request at a time; an answer older than the last one asked for is dropped.
 */
let asked = 0
const announced = new Set<string>()
async function refresh(): Promise<void> {
  const id = workout.value?.id
  if (!id) return
  const call = ++asked
  try {
    const server = await api.get('/api/workouts/{id}', { path: { id } })
    if (call !== asked) return
    await session.merge(server)
    const fresh = server.exercises.flatMap(exercise => exercise.newRecords.map(record => ({ exercise, record, key: `${exercise.index}-${record.kind}-${record.loadKg}-${record.value}` }))).filter(({ key }) => !announced.has(key))
    fresh.forEach(({ key }) => announced.add(key))
    if (fresh.length) {
      navigator.vibrate?.(100)
      notice.value = `Nouveau record\u00A0! ${fresh.map(({ exercise, record }) => `${exercise.name}\u00A0— ${formatRecord(record, exercise.measurement)}`).join(' ; ')}`
    }
  }
  catch {
    // Offline, or not received yet: the device carries on, records come later.
  }
}

async function hourReached(): Promise<void> {
  if (!workout.value || workout.value.hourAlerted) return
  await session.markHourAlerted()
  navigator.vibrate?.([300, 150, 300])
  notice.value = '1\u00A0h d’entraînement\u00A0: pense à ta récupération.'
}

const exercise = computed(() => workout.value?.exercises[workout.value.current] ?? null)
const sets = computed(() => workout.value?.sets ?? [])
const setsOfExercise = computed(() => (exercise.value ? sets.value.filter(set => set.exerciseIndex === exercise.value!.index) : []))
const working = computed(() => (exercise.value ? workingSetsOf(sets.value, exercise.value.index).length : 0))
const kind = computed(() => (exercise.value ? nextKind(exercise.value, sets.value) : 'normal'))
const warmups = computed(() => setsOfExercise.value.filter(set => set.kind === 'warmup').length)
const left = computed(() => (workout.value ? unfinished(workout.value.exercises, sets.value) : []))
const allDone = computed(() => workout.value !== null && workout.value !== undefined && workout.value.exercises.length > 0 && left.value.length === 0 && !(exercise.value && dropSetDue(exercise.value, sets.value)))
const anotherSet = ref(false)

/** Where the exercise sits: "Superset A · 1/2 — ensuite : Élévations latérales (sans repos)". */
const superset = computed(() => {
  const current = exercise.value
  const all = workout.value?.exercises ?? []
  if (!current) return null
  const block = all.filter(other => other.block === current.block)
  if (block.length < 2) return null
  const position = block.findIndex(other => other.index === current.index)
  const letter = blockLetter([...new Set(all.map(other => other.block))].indexOf(current.block))
  const then = block[position + 1]
  return `Superset ${letter} · ${position + 1}/${block.length}\u00A0— ${then ? `ensuite\u00A0: ${then.name} (sans repos)` : `puis repos\u00A0: ${formatRest(current.restSeconds)}`}`
})

const previousWorking = computed(() => exercise.value?.previous?.sets.filter(isWorking) ?? [])

/**
 * Where the values of the next set start: the same kind of set done just before, else (for
 * work) the suggestion or last time; a warm-up at half the working load; the drop set a quarter
 * lighter than the last working set.
 */
const initial = computed<Partial<SetValues>>(() => {
  const current = exercise.value
  if (!current) return {}
  const own = setsOfExercise.value
  const target = current.suggestion ?? previousWorking.value[0] ?? null
  const lastWorking = own.filter(isWorking).at(-1)
  const round = (value: number) => Math.max(0, Math.round(value / current.incrementKg) * current.incrementKg)
  if (kind.value === 'drop' && lastWorking) return { kind: 'drop', loadKg: round((lastWorking.loadKg ?? 0) * 0.75), reps: lastWorking.reps, seconds: lastWorking.seconds }
  if (kind.value === 'warmup') {
    const lastWarmup = own.filter(set => set.kind === 'warmup').at(-1)
    if (lastWarmup) return { kind: 'warmup', loadKg: lastWarmup.loadKg, reps: lastWarmup.reps, seconds: lastWarmup.seconds }
    return { kind: 'warmup', loadKg: target?.loadKg ? round(target.loadKg / 2) : null, reps: current.measurement === 'duration' ? null : current.repMax, seconds: current.measurement === 'duration' ? current.repMin : null }
  }
  if (lastWorking) return { kind: 'normal', loadKg: lastWorking.loadKg, reps: lastWorking.reps, seconds: lastWorking.seconds, rir: lastWorking.rir }
  if (target) return { kind: 'normal', loadKg: target.loadKg, reps: target.reps, seconds: target.seconds }
  return { kind: 'normal', reps: current.measurement === 'duration' ? null : current.repMax, seconds: current.measurement === 'duration' ? current.repMin : null }
})
/** A new form for each set, so its values start again from the right place. */
const formKey = computed(() => `${workout.value?.current}-${sets.value.length}`)

function focusExercise(): void {
  void nextTick(() => heading.value?.focus())
}

async function record(values: SetValues): Promise<void> {
  const before = workout.value?.current
  const set = await session.recordSet(values)
  const current = workout.value
  if (!set || !current) return
  const moved = current.current !== before ? ` Suivant\u00A0: ${current.exercises[current.current]?.name}.` : ''
  notice.value = `Série enregistrée\u00A0: ${formatSet(set, current.exercises[set.exerciseIndex]!.measurement)}.${moved}`
  anotherSet.value = false
  await nextTick()
  if (current.rest) restTimer.value?.focus()
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
  setForm.value?.focus()
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

const listing = ref(false)
const adding = ref(false)
async function jump(index: number): Promise<void> {
  listing.value = false
  await goTo(index)
}
async function add(chosen: Exercise): Promise<void> {
  adding.value = false
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
    class="workout"
  >
    <header class="head">
      <div class="head__text">
        <h1
          id="page-title"
          class="head__title"
        >
          {{ workout.programDay?.dayName ?? 'Séance libre' }}
        </h1>
        <p class="head__meta">
          <template v-if="workout.exercises.length">
            <span aria-hidden="true">Exercice {{ workout.current + 1 }}/{{ workout.exercises.length }} · </span>
            <span class="visually-hidden">Exercice {{ workout.current + 1 }} sur {{ workout.exercises.length }}, </span>
          </template>
          <WorkoutClock
            :started-at="workout.startedAt"
            @long="hourReached"
          />
        </p>
      </div>
      <UiButton
        variant="secondary"
        @click="finishing = true"
      >
        Terminer
      </UiButton>
    </header>

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
      class="exercise"
      aria-labelledby="exercise-title"
    >
      <p
        v-if="superset"
        class="exercise__kind"
      >
        {{ superset }}
      </p>
      <h2
        id="exercise-title"
        ref="heading"
        class="exercise__name"
        tabindex="-1"
      >
        {{ exercise.name }}
      </h2>
      <p class="exercise__plan">
        {{ prescription(exercise, { rest: false, tempo: true }) }}
      </p>
      <p
        v-for="cue in [exercise.note, exercise.programNotes].filter(Boolean)"
        :key="cue!"
        class="exercise__cue"
      >
        {{ cue }}
      </p>
      <p class="exercise__progress">
        <template v-if="kind === 'warmup'">
          Échauffement {{ warmups + 1 }}/{{ exercise.warmupSets }}
        </template>
        <template v-else-if="kind === 'drop'">
          Drop set, sans repos
        </template>
        <template v-else-if="working < exercise.sets">
          Série {{ working + 1 }}/{{ exercise.sets }}
        </template>
        <template v-else>
          Séries prévues faites
        </template>
      </p>
      <div
        v-if="previousWorking.length || exercise.suggestion || exercise.newRecords.length"
        class="exercise__facts"
      >
        <div v-if="previousWorking.length">
          <p class="exercise__fact-title">
            Dernière fois
          </p>
          <ol class="exercise__previous">
            <li
              v-for="set in previousWorking"
              :key="set.id"
            >
              {{ formatSet(set, exercise.measurement) }}
            </li>
          </ol>
        </div>
        <p v-if="exercise.suggestion">
          <span class="exercise__fact-title">Suggestion&nbsp;:</span> {{ formatSuggestion(exercise.suggestion, exercise) }}
        </p>
        <p
          v-if="exercise.newRecords.length"
          class="exercise__records"
        >
          <span class="exercise__fact-title">Records battus aujourd’hui&nbsp;:</span> {{ exercise.newRecords.map(record => formatRecord(record, exercise!.measurement)).join(' · ') }}
        </p>
      </div>

      <div
        v-if="editing && editingExercise"
        class="correction"
      >
        <h3 class="correction__title">
          Correction de la série {{ editingNumber }} ({{ labelOf(SET_KINDS, editing.kind) }})
        </h3>
        <SetForm
          ref="setForm"
          :key="`edit-${editing.id}`"
          :exercise="editingExercise"
          :initial="editing"
          submit-label="Enregistrer la correction"
          correcting
          @submit="correct"
          @cancel="cancelCorrecting"
        />
      </div>
      <UiCard v-else-if="allDone && !anotherSet">
        <p class="done-card__text">
          Toutes les séries prévues sont faites.
        </p>
        <div class="form__actions">
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
        ref="setForm"
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

    <nav
      class="moves"
      aria-label="Exercices de la séance"
    >
      <UiButton
        variant="secondary"
        :disabled="workout.current === 0"
        @click="goTo(workout.current - 1)"
      >
        Précédent
      </UiButton>
      <UiButton
        variant="secondary"
        aria-label="Tous les exercices"
        :disabled="!workout.exercises.length"
        @click="listing = true"
      >
        <ListOrdered
          :size="18"
          aria-hidden="true"
        />
      </UiButton>
      <UiButton
        variant="secondary"
        :disabled="workout.current >= workout.exercises.length - 1"
        @click="goTo(workout.current + 1)"
      >
        Suivant
      </UiButton>
    </nav>
    <UiButton
      variant="ghost"
      @click="adding = true"
    >
      Ajouter un exercice
    </UiButton>

    <UiDrawer
      v-model:open="listing"
      title="Exercices de la séance"
    >
      <ol class="list">
        <li
          v-for="planned in workout.exercises"
          :key="planned.index"
        >
          <button
            type="button"
            class="list__item"
            :aria-current="planned.index === workout.current ? 'step' : undefined"
            @click="jump(planned.index)"
          >
            <span class="list__name">{{ planned.name }}</span>
            <span class="list__detail">{{ workingSetsOf(workout.sets, planned.index).length }}/{{ planned.sets }} séries · {{ formatTarget(planned.repMin, planned.repMax, planned.measurement) }}</span>
          </button>
        </li>
      </ol>
    </UiDrawer>

    <UiDrawer
      v-model:open="adding"
      title="Ajouter un exercice"
    >
      <ExercisePicker
        v-if="adding"
        :create="false"
        @pick="add"
      />
    </UiDrawer>

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
  <p
    v-else
    class="loading"
  >
    <span
      id="page-title"
      class="head__title"
    >Chargement de la séance…</span>
  </p>
</template>

<style scoped>
.workout {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}

.head__title {
  font-size: var(--text-xl, 1.25rem);
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.head__meta {
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
}

.exercise {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.exercise__kind {
  color: var(--color-accent-text);
  font-size: var(--text-sm);
  font-weight: 700;
}

.exercise__name {
  font-size: clamp(1.5rem, 7vw, 2rem);
  font-weight: 800;
  line-height: 1.15;
}

.exercise__name:focus {
  outline: none;
}

.exercise__plan {
  color: var(--color-text-muted);
  font-weight: 600;
}

.exercise__progress {
  font-size: var(--text-lg, 1.125rem);
  font-weight: 800;
}

.exercise__cue {
  padding: var(--space-2) var(--space-3);
  background: var(--color-surface-raised);
  border-left: 3px solid var(--color-accent);
  border-radius: var(--radius-sm);
}

.exercise__facts {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  font-variant-numeric: tabular-nums;
}

.exercise__fact-title {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.exercise__previous {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1) var(--space-3);
  font-weight: 700;
}

.exercise__records {
  font-weight: 700;
}

.correction {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-3);
  border: 2px solid var(--color-accent);
  border-radius: var(--radius-md);
}

.correction__title {
  font-weight: 700;
}

.done-card__text {
  margin-bottom: var(--space-3);
  font-weight: 700;
}

.moves {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  gap: var(--space-2);
}

.list {
  display: flex;
  flex-direction: column;
}

.list__item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
  width: 100%;
  min-height: var(--tap-target);
  padding: var(--space-2) 0;
  background: transparent;
  border: 0;
  border-top: 1px solid var(--color-border);
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.list__item[aria-current='step'] .list__name {
  color: var(--color-accent-text);
}

.list__name {
  font-weight: 600;
}

.list__detail {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
