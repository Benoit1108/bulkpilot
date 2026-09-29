<script setup lang="ts">
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-vue-next'
import ExercisePicker from '~/features/training/components/ExercisePicker.vue'
import { STRATEGIES, blockLabel, blockLetter, plannedFrom, type Exercise, type Measurement, type PlannedExerciseInput, type Program, type ProgramInput, type Strategy } from '~/features/training/training'
import { parseAmount } from '~/services/text/numbers'

/**
 * Days of blocks of exercises; a block of several is a superset. Saving a change to the days
 * records a new version of the program (the previous one stays for past workouts).
 */
const props = defineProps<{ program?: Program | null }>()
const emit = defineEmits<{ saved: [program: Program] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()

const MAX_DAYS = 7 // ProgramVersion::MAX_DAYS
const MAX_BLOCKS = 15 // TrainingDay::MAX_BLOCKS
const MAX_IN_BLOCK = 4 // ExerciseBlock::MAX_EXERCISES

type Amount = number | string | null
interface PlannedRow {
  row: number
  exerciseId: string
  name: string
  measurement: Measurement
  available: boolean
  sets: Amount
  repMin: Amount
  repMax: Amount
  targetRir: Amount
  restSeconds: Amount
  strategy: Strategy | null
  incrementKg: Amount
  warmupSets: Amount
  dropSet: boolean
  tempo: string
  notes: string
}
interface BlockRow { row: number, exercises: PlannedRow[] }
interface DayRow { row: number, name: string, blocks: BlockRow[] }

let nextRow = 0
const name = ref(props.program?.name ?? '')
const days = ref<DayRow[]>(props.program?.days.map(day => ({
  row: nextRow++,
  name: day.name,
  blocks: day.blocks.map(block => ({
    row: nextRow++,
    exercises: block.exercises.map(exercise => ({ row: nextRow++, ...exercise, tempo: exercise.tempo ?? '', notes: exercise.notes ?? '' })),
  })),
})) ?? [{ row: nextRow++, name: 'Jour A', blocks: [] }])

function fromExercise(exercise: Exercise): PlannedRow {
  return { row: nextRow++, name: exercise.name, measurement: exercise.measurement, available: true, ...plannedFrom(exercise), tempo: exercise.tempo ?? '', notes: '' }
}

/** Controls are found again after the list re-rendered (moves, removals): focus stays with the user. */
const root = ref<HTMLElement | null>(null)
const formId = useId()
function focusLater(selector: string, fallback?: string): void {
  void nextTick(() => {
    const target = root.value?.querySelector<HTMLElement>(`${selector}:not([disabled])`) ?? (fallback ? root.value?.querySelector<HTMLElement>(fallback) : null)
    target?.focus()
  })
}
const moved = ref('')

/** Where the picked exercise goes: a new block of the day, or an existing block (superset). */
const target = ref<{ day: number, block: number | null } | null>(null)
const picking = computed({
  get: () => target.value !== null,
  set: (open: boolean) => {
    if (!open) target.value = null
  },
})

function pick(exercise: Exercise): void {
  const where = target.value
  if (!where) return
  const day = days.value[where.day]
  if (!day) return
  const block = where.block === null ? undefined : day.blocks[where.block]
  if (block) {
    // A superset rests after its last exercise: the ones before it go on without rest.
    block.exercises.forEach(member => (member.restSeconds = 0))
    block.exercises.push(fromExercise(exercise))
  }
  else day.blocks.push({ row: nextRow++, exercises: [fromExercise(exercise)] })
  target.value = null
}

function addDay(): void {
  days.value.push({ row: nextRow++, name: `Jour ${blockLetter(days.value.length)}`, blocks: [] })
  focusLater(`[data-day-name="${days.value.at(-1)!.row}"] input`)
}

function removeDay(index: number): void {
  days.value.splice(index, 1)
  focusLater('[data-add-day]')
}

function moveBlock(day: DayRow, index: number, by: -1 | 1): void {
  const [block] = day.blocks.splice(index, 1)
  if (!block) return
  day.blocks.splice(index + by, 0, block)
  moved.value = `${block.exercises[0]?.name} en position ${index + by + 1} sur ${day.blocks.length}.`
  focusLater(`[data-move="${block.row}-${by}"]`, `[data-move="${block.row}-${-by}"]`)
}

function removeExercise(day: DayRow, blockIndex: number, exerciseIndex: number): void {
  const block = day.blocks[blockIndex]
  if (!block) return
  const [removed] = block.exercises.splice(exerciseIndex, 1)
  const last = block.exercises.at(-1)
  // The round's rest lives on the last exercise: it passes to the one now last.
  if (removed && last && exerciseIndex === block.exercises.length) last.restSeconds = removed.restSeconds
  if (!block.exercises.length) day.blocks.splice(blockIndex, 1)
  focusLater(`[data-add="${day.row}"]`)
}

function whole(value: Amount): number | undefined {
  const amount = parseAmount(value)
  return amount === null ? undefined : Math.round(amount)
}

function planned(row: PlannedRow): PlannedExerciseInput {
  return {
    exerciseId: row.exerciseId,
    sets: whole(row.sets),
    repMin: whole(row.repMin),
    repMax: whole(row.repMax),
    targetRir: whole(row.targetRir) ?? null,
    restSeconds: whole(row.restSeconds),
    strategy: row.strategy ?? 'double_progression',
    incrementKg: parseAmount(row.incrementKg) ?? undefined,
    warmupSets: whole(row.warmupSets) ?? 0,
    dropSet: row.dropSet,
    tempo: row.tempo.trim() || null,
    notes: row.notes.trim() || null,
  } as PlannedExerciseInput
}

function body(): ProgramInput {
  return {
    name: name.value,
    days: days.value.map(day => ({ name: day.name, blocks: day.blocks.map(block => ({ exercises: block.exercises.map(planned) })) })),
  }
}

async function submit(): Promise<void> {
  await run(async () => {
    const saved = props.program
      ? await api.put('/api/programs/{id}', body(), { path: { id: props.program.id } })
      : await api.post('/api/programs', body())
    emit('saved', saved)
  })
}

const path = (d: number, b: number, e: number, field: string) => fields.value[`days[${d}].blocks[${b}].exercises[${e}].${field}`]
const unit = (row: PlannedRow) => (row.measurement === 'duration' ? 's' : 'reps')
const dayName = (day: DayRow, index: number) => day.name || `Jour ${index + 1}`
const blockTitle = (block: BlockRow, index: number) => `${block.exercises.length > 1 ? 'Superset' : 'Bloc'} ${blockLetter(index)}`
/** In a superset, the rest comes after the whole round. */
const restLabel = (block: BlockRow) => block.exercises.length > 1 ? 'Repos après le tour' : 'Repos'

const rangeLabel = (row: PlannedRow, end: 'min.' | 'max.') => `${row.measurement === 'duration' ? 'Durée' : 'Reps'} ${end}`
</script>

<template>
  <div>
    <form
      ref="root"
      class="form"
      novalidate
      @submit.prevent="submit"
    >
      <p
        class="visually-hidden"
        role="status"
      >
        {{ moved }}
      </p>
      <UiAlert
        v-if="message"
        tone="error"
      >
        {{ message }}
      </UiAlert>
      <UiInput
        v-model="name"
        label="Nom du programme"
        required
        :maxlength="100"
        hint="Par exemple «&nbsp;PPL Masse&nbsp;» ou «&nbsp;Full Body&nbsp;»."
        :error="fields.name"
      />
      <p
        v-if="fields.days"
        class="form__error"
      >
        {{ fields.days }}
      </p>

      <fieldset
        v-for="(day, d) in days"
        :key="day.row"
        class="day"
      >
        <legend class="visually-hidden">
          {{ dayName(day, d) }}
        </legend>
        <div
          class="day__head"
          :data-day-name="day.row"
        >
          <UiInput
            v-model="day.name"
            :label="`Nom du jour ${d + 1}`"
            :maxlength="40"
            hint="Par exemple «&nbsp;Push&nbsp;» ou «&nbsp;Jambes&nbsp;»."
            :error="fields[`days[${d}].name`]"
          />
          <UiConfirmButton
            v-if="days.length > 1"
            label="Retirer ce jour"
            confirm-label="Confirmer"
            variant="ghost"
            @confirm="removeDay(d)"
          />
        </div>
        <p
          v-if="fields[`days[${d}].blocks`]"
          class="form__error"
        >
          {{ fields[`days[${d}].blocks`] }}
        </p>

        <ol class="blocks">
          <li
            v-for="(block, b) in day.blocks"
            :key="block.row"
            class="block"
            :class="{ 'block--superset': block.exercises.length > 1 }"
          >
            <div class="block__head">
              <span class="block__kind">{{ blockTitle(block, b) }}</span>
              <span class="block__moves">
                <UiButton
                  variant="ghost"
                  :disabled="b === 0"
                  :data-move="`${block.row}--1`"
                  :aria-label="`Monter ${block.exercises[0]?.name}`"
                  @click="moveBlock(day, b, -1)"
                >
                  <ArrowUp
                    :size="18"
                    aria-hidden="true"
                  />
                </UiButton>
                <UiButton
                  variant="ghost"
                  :disabled="b === day.blocks.length - 1"
                  :data-move="`${block.row}-1`"
                  :aria-label="`Descendre ${block.exercises[0]?.name}`"
                  @click="moveBlock(day, b, 1)"
                >
                  <ArrowDown
                    :size="18"
                    aria-hidden="true"
                  />
                </UiButton>
              </span>
            </div>

            <div
              v-for="(exercise, e) in block.exercises"
              :key="exercise.row"
              class="planned"
              role="group"
              :aria-labelledby="`${formId}-planned-${exercise.row}`"
            >
              <div class="planned__head">
                <p
                  :id="`${formId}-planned-${exercise.row}`"
                  class="planned__name"
                >
                  <span>{{ blockLabel(b, e, block.exercises.length) }}</span>
                  {{ exercise.name }}<template v-if="!exercise.available">
                    (supprimé)
                  </template>
                </p>
                <UiButton
                  variant="ghost"
                  :aria-label="`Retirer ${exercise.name} de ${day.name}`"
                  @click="removeExercise(day, b, e)"
                >
                  <Trash2
                    :size="18"
                    aria-hidden="true"
                  />
                </UiButton>
              </div>
              <div class="planned__grid">
                <UiInput
                  v-model="exercise.sets"
                  label="Séries"
                  type="number"
                  inputmode="numeric"
                  :error="path(d, b, e, 'sets')"
                />
                <UiInput
                  v-model="exercise.repMin"
                  :label="rangeLabel(exercise, 'min.')"
                  type="number"
                  inputmode="numeric"
                  :suffix="unit(exercise)"
                  :error="path(d, b, e, 'repMin')"
                />
                <UiInput
                  v-model="exercise.repMax"
                  :label="rangeLabel(exercise, 'max.')"
                  type="number"
                  inputmode="numeric"
                  :suffix="unit(exercise)"
                  :error="path(d, b, e, 'repMax')"
                />
                <UiInput
                  v-model="exercise.targetRir"
                  label="RIR"
                  type="number"
                  inputmode="numeric"
                  :error="path(d, b, e, 'targetRir')"
                />
                <UiInput
                  v-if="e === block.exercises.length - 1"
                  v-model="exercise.restSeconds"
                  :label="restLabel(block)"
                  type="number"
                  inputmode="numeric"
                  suffix="s"
                  :error="path(d, b, e, 'restSeconds')"
                />
                <p
                  v-else
                  class="planned__hint"
                >
                  Enchaîné sans repos avec le suivant.
                </p>
              </div>
              <details class="planned__more">
                <summary>Progression, échauffement, tempo, consignes</summary>
                <div class="planned__options">
                  <UiSelect
                    v-model="exercise.strategy"
                    label="Progression"
                    :nullable="false"
                    :options="STRATEGIES"
                    :error="path(d, b, e, 'strategy')"
                  />
                  <p class="planned__hint">
                    {{ STRATEGIES.find(strategy => strategy.value === exercise.strategy)?.description }}
                  </p>
                  <div class="planned__grid">
                    <UiInput
                      v-if="exercise.measurement !== 'duration'"
                      v-model="exercise.incrementKg"
                      label="Palier de charge"
                      type="number"
                      suffix="kg"
                      :error="path(d, b, e, 'incrementKg')"
                    />
                    <UiInput
                      v-model="exercise.warmupSets"
                      label="Échauffement"
                      type="number"
                      inputmode="numeric"
                      suffix="séries"
                      :error="path(d, b, e, 'warmupSets')"
                    />
                    <UiInput
                      v-model="exercise.tempo"
                      label="Tempo"
                      literal
                      :maxlength="15"
                      :error="path(d, b, e, 'tempo')"
                    />
                  </div>
                  <UiCheckbox
                    v-model="exercise.dropSet"
                    label="Drop set après la dernière série"
                  />
                  <UiInput
                    v-model="exercise.notes"
                    label="Consigne pour ce programme"
                    :maxlength="200"
                    :error="path(d, b, e, 'notes')"
                  />
                </div>
              </details>
            </div>

            <UiButton
              v-if="block.exercises.length < MAX_IN_BLOCK"
              variant="ghost"
              @click="target = { day: d, block: b }"
            >
              {{ block.exercises.length > 1 ? 'Ajouter au superset' : 'Faire un superset' }}<span class="visually-hidden"> avec {{ block.exercises[0]?.name }}</span>
            </UiButton>
          </li>
        </ol>

        <UiButton
          variant="secondary"
          :disabled="day.blocks.length >= MAX_BLOCKS"
          :data-add="day.row"
          @click="target = { day: d, block: null }"
        >
          Ajouter un exercice à {{ day.name || 'ce jour' }}
        </UiButton>
      </fieldset>

      <UiButton
        variant="secondary"
        :disabled="days.length >= MAX_DAYS"
        data-add-day
        @click="addDay"
      >
        Ajouter un jour
      </UiButton>

      <UiButton
        type="submit"
        :loading="pending"
      >
        {{ program ? 'Enregistrer' : 'Créer le programme' }}
      </UiButton>
      <p
        v-if="program"
        class="form__note"
      >
        Changer les jours enregistre une nouvelle version du programme&nbsp;; tes séances passées gardent la leur.
      </p>
    </form>

    <UiDrawer
      v-model:open="picking"
      title="Choisir un exercice"
    >
      <ExercisePicker
        v-if="picking"
        @pick="pick"
      />
    </UiDrawer>
  </div>
</template>

<style scoped>
.form__error {
  color: var(--color-danger);
  font-size: var(--text-sm);
}

.form__note {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.day {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  min-width: 0;
  margin: 0;
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.day__head,
.planned__head {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: var(--space-2);
}

.blocks {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.block {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.block--superset {
  padding-left: var(--space-2);
  border-left: 3px solid var(--color-accent);
}

.block__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}

.block__kind {
  color: var(--color-text-muted);
  font-size: var(--text-xs);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.block__moves {
  display: flex;
}

.planned {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding-top: var(--space-2);
  border-top: 1px solid var(--color-border);
}

.planned__name {
  display: flex;
  gap: var(--space-2);
  font-weight: 600;
}

.planned__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(6.5rem, 1fr));
  gap: var(--space-2);
}

.planned__more summary {
  display: flex;
  align-items: center;
  min-height: var(--tap-target);
  color: var(--color-accent-text);
  font-weight: 600;
  cursor: pointer;
}

.planned__options {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.planned__hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
