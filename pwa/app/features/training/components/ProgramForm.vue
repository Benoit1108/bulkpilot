<script setup lang="ts">
import { MAX_DAYS, useProgramDraft, type DayRow } from '~/features/training/programDraft'
import type { Exercise, Program } from '~/features/training/training'
import ExercisePicker from './ExercisePicker.vue'
import ProgramDayEditor from './ProgramDayEditor.vue'

/**
 * Days of blocks of exercises; a block of several is a superset. Saving a change to the days
 * records a new version of the program (the previous one stays for past workouts).
 */
const props = defineProps<{ program?: Program | null }>()
const emit = defineEmits<{ saved: [program: Program] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const draft = useProgramDraft(props.program)
const { name, days } = draft
const full = computed(() => days.value.length >= MAX_DAYS)

/** Controls are found again after the list re-rendered (moves, removals): focus stays with the user. */
const root = ref<HTMLElement | null>(null)
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
  if (target.value) draft.add(exercise, target.value.day, target.value.block)
  target.value = null
}

function addDay(): void {
  const day = draft.addDay()
  focusLater(`[data-day-name="${day.row}"] input`)
}

function removeDay(index: number): void {
  draft.removeDay(index)
  focusLater('[data-add-day]')
}

function moveBlock(day: DayRow, index: number, by: -1 | 1): void {
  const block = draft.moveBlock(day, index, by)
  if (!block) return
  moved.value = `${block.exercises[0]?.name} en position ${index + by + 1} sur ${day.blocks.length}.`
  focusLater(`[data-move="${block.row}-${by}"]`, `[data-move="${block.row}-${-by}"]`)
}

function removeExercise(day: DayRow, blockIndex: number, exerciseIndex: number): void {
  draft.removeExercise(day, blockIndex, exerciseIndex)
  focusLater(`[data-add="${day.row}"]`)
}

const { markSaved } = useUnsavedChanges(() => draft.body())

async function submit(): Promise<void> {
  await run(async () => {
    const sent = draft.body()
    const saved = props.program
      ? await api.put('/api/programs/{id}', sent, { path: { id: props.program.id } })
      : await api.post('/api/programs', sent)
    markSaved(sent)
    emit('saved', saved)
  })
}
</script>

<template>
  <div>
    <form
      ref="root"
      class="flex flex-col gap-4"
      novalidate
      @submit.prevent="submit"
    >
      <p
        class="sr-only"
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
        class="text-sm text-danger"
      >
        {{ fields.days }}
      </p>

      <ProgramDayEditor
        v-for="(day, d) in days"
        :key="day.row"
        v-model="days[d]!"
        :index="d"
        :removable="days.length !== 1"
        :fields="fields"
        @remove="removeDay(d)"
        @pick="target = { day: d, block: $event }"
        @move="(b, by) => moveBlock(day, b, by)"
        @remove-exercise="(b, e) => removeExercise(day, b, e)"
      />

      <UiButton
        variant="secondary"
        :disabled="full"
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
        class="text-sm text-muted"
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
