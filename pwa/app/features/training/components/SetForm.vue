<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next'
import type { SetValues } from '~/features/training/session'
import { MAIN_KINDS, SET_KINDS, type SetKind, type WorkoutExercise } from '~/features/training/workout'
import { parseAmount } from '~/services/text/numbers'

/**
 * One set, as fast as possible (spec §42): values start from the right place, steppers move by
 * the exercise's increment, kind and RIR are one tap. Values are checked against what the API
 * accepts, so that a set never waits in the queue only to be refused.
 */
const props = defineProps<{
  exercise: WorkoutExercise
  initial: Partial<SetValues>
  submitLabel: string
  /** Correcting a set already done: a cancel button. */
  correcting?: boolean
}>()
const emit = defineEmits<{ submit: [values: SetValues], cancel: [] }>()

// The limits of WorkoutSet on the API.
const MAX_LOAD_KG = 1000
const MAX_REPS = 300
const MAX_SECONDS = 3600

type Amount = number | string | null
const kind = ref<SetKind>(props.initial.kind ?? 'normal')
const load = ref<Amount>(props.initial.loadKg ?? '')
const reps = ref<Amount>(props.initial.reps ?? '')
const seconds = ref<Amount>(props.initial.seconds ?? '')
const rir = ref<number | null>(props.initial.rir ?? null)
const errors = reactive<{ load?: string, reps?: string, seconds?: string }>({})
const stepped = ref('')
const loadField = ref<{ focus: () => void } | null>(null)
const countField = ref<{ focus: () => void } | null>(null)
const moreKinds = ref(!MAIN_KINDS.includes(kind.value))
const kindName = useId()
const rirName = useId()

const timed = computed(() => props.exercise.measurement === 'duration')
const bodyweight = computed(() => props.exercise.measurement === 'bodyweight')
const kinds = computed(() => SET_KINDS.filter(option => moreKinds.value || MAIN_KINDS.includes(option.value)))
const kindHint = computed(() => SET_KINDS.find(option => option.value === kind.value)?.hint)
const RIR_VALUES = [0, 1, 2, 3, 4, 5]
const kg = (value: number) => value.toLocaleString('fr-FR')

defineExpose({ focus: () => (timed.value || bodyweight.value ? countField : loadField).value?.focus() })

/** Template refs are unwrapped: fields are named. */
const fields = { load, reps, seconds }
const spoken = { load: 'Charge', reps: 'Répétitions', seconds: 'Durée' } as const
function step(field: keyof typeof fields, by: number, min = 0): void {
  const target = fields[field]
  const value = Math.max(min, Math.round(((parseAmount(target.value) ?? 0) + by) * 100) / 100)
  target.value = value
  stepped.value = `${spoken[field]}\u00A0: ${field === 'load' ? `${kg(value)}\u00A0kg` : field === 'seconds' ? `${value}\u00A0s` : value}`
}

function toggleRir(value: number): void {
  rir.value = rir.value === value ? null : value
}

function check(): SetValues | null {
  const loadKg = parseAmount(load.value)
  const count = parseAmount(reps.value)
  const duration = parseAmount(seconds.value)
  errors.load = undefined
  errors.reps = undefined
  errors.seconds = undefined
  if (timed.value) {
    if (duration === null || duration < 1 || duration > MAX_SECONDS) errors.seconds = 'Une durée de 1 à 3\u202F600 secondes.'
  }
  else {
    if ((!bodyweight.value && loadKg === null) || (loadKg !== null && (loadKg < 0 || loadKg > MAX_LOAD_KG))) errors.load = bodyweight.value ? 'Un lest de 0 à 1\u202F000 kg.' : 'Une charge de 0 à 1\u202F000 kg.'
    if (count === null || count < 0 || count > MAX_REPS) errors.reps = 'De 0 à 300 répétitions.'
  }
  if (errors.load) loadField.value?.focus()
  else if (errors.reps || errors.seconds) countField.value?.focus()
  if (errors.load || errors.reps || errors.seconds) return null
  return {
    kind: kind.value,
    loadKg: timed.value ? null : loadKg,
    reps: timed.value ? null : Math.round(count ?? 0),
    seconds: timed.value ? Math.round(duration ?? 0) : null,
    rir: rir.value,
  }
}

function submit(): void {
  const values = check()
  if (values) emit('submit', values)
}
</script>

<template>
  <form
    class="set"
    novalidate
    @submit.prevent="submit"
  >
    <fieldset class="set__choices">
      <legend class="set__legend">
        Type de série
      </legend>
      <div class="set__chips">
        <label
          v-for="option in kinds"
          :key="option.value"
          class="chip"
        >
          <input
            v-model="kind"
            type="radio"
            :name="kindName"
            :value="option.value"
            class="visually-hidden"
          >
          <span>{{ option.label }}</span>
        </label>
        <button
          v-if="!moreKinds"
          type="button"
          class="chip chip--more"
          @click="moreKinds = true"
        >
          Autre…
        </button>
      </div>
      <p class="set__hint">
        {{ kindHint }}
      </p>
    </fieldset>

    <div
      v-if="!timed"
      class="set__field"
    >
      <UiInput
        ref="loadField"
        v-model="load"
        :label="bodyweight ? 'Lest' : 'Charge'"
        type="number"
        suffix="kg"
        :hint="bodyweight ? 'Facultatif.' : undefined"
        :error="errors.load"
      />
      <div class="set__steps">
        <UiButton
          variant="secondary"
          :aria-label="`Retirer ${kg(exercise.incrementKg)} kg`"
          @click="step('load', -exercise.incrementKg)"
        >
          <Minus
            :size="20"
            aria-hidden="true"
          />
        </UiButton>
        <UiButton
          variant="secondary"
          :aria-label="`Ajouter ${kg(exercise.incrementKg)} kg`"
          @click="step('load', exercise.incrementKg)"
        >
          <Plus
            :size="20"
            aria-hidden="true"
          />
        </UiButton>
      </div>
    </div>
    <div class="set__field">
      <UiInput
        v-if="timed"
        ref="countField"
        v-model="seconds"
        label="Durée"
        type="number"
        inputmode="numeric"
        suffix="s"
        :error="errors.seconds"
      />
      <UiInput
        v-else
        ref="countField"
        v-model="reps"
        label="Répétitions"
        type="number"
        inputmode="numeric"
        :error="errors.reps"
      />
      <div class="set__steps">
        <UiButton
          variant="secondary"
          :aria-label="timed ? 'Retirer 5 secondes' : 'Une répétition de moins'"
          @click="timed ? step('seconds', -5, 1) : step('reps', -1)"
        >
          <Minus
            :size="20"
            aria-hidden="true"
          />
        </UiButton>
        <UiButton
          variant="secondary"
          :aria-label="timed ? 'Ajouter 5 secondes' : 'Une répétition de plus'"
          @click="timed ? step('seconds', 5, 1) : step('reps', 1)"
        >
          <Plus
            :size="20"
            aria-hidden="true"
          />
        </UiButton>
      </div>
    </div>
    <p
      class="visually-hidden"
      role="status"
    >
      {{ stepped }}
    </p>

    <fieldset class="set__choices">
      <legend class="set__legend">
        RIR <span class="set__legend-hint">(reps en réserve, 0 = échec, facultatif)</span>
      </legend>
      <div class="set__chips">
        <label
          v-for="value in RIR_VALUES"
          :key="value"
          class="chip chip--small"
        >
          <input
            type="radio"
            :name="rirName"
            :value="value"
            :checked="rir === value"
            class="visually-hidden"
            @click="toggleRir(value)"
          >
          <span>{{ value }}</span>
        </label>
      </div>
    </fieldset>

    <div class="set__actions">
      <UiButton
        type="submit"
        class="set__submit"
      >
        {{ submitLabel }}
      </UiButton>
      <UiButton
        v-if="correcting"
        variant="ghost"
        @click="emit('cancel')"
      >
        Annuler
      </UiButton>
    </div>
  </form>
</template>

<style scoped>
.set {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.set__choices {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.set__legend {
  margin-bottom: var(--space-1);
  font-size: var(--text-sm);
  font-weight: 600;
}

.set__legend-hint,
.set__hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-weight: 400;
}

.set__hint {
  margin-top: var(--space-1);
}

.set__chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: var(--tap-target);
  min-height: var(--tap-target);
  padding: 0 var(--space-3);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: inherit;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.chip:has(input:checked) {
  background: var(--color-accent-fill);
  border-color: var(--color-accent-fill);
  color: var(--color-accent-contrast);
}

.chip:has(input:focus-visible),
.chip--more:focus-visible {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.chip--small {
  padding: 0;
  font-variant-numeric: tabular-nums;
}

.chip--more {
  color: var(--color-accent-text);
}

.set__field {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: start;
  gap: var(--space-2);
}

.set__field :deep(.ui-input__field) {
  font-size: 1.5rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.set__steps {
  display: flex;
  gap: var(--space-2);
  margin-top: calc(var(--text-sm) * 1.5 + var(--space-1));
}

.set__steps :deep(.ui-button) {
  min-width: calc(var(--tap-target) * 1.2);
}

.set__actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.set__submit {
  min-height: calc(var(--tap-target) * 1.25);
  font-size: var(--text-lg, 1.125rem);
}
</style>
