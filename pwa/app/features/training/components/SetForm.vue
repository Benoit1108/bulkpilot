<script setup lang="ts">
import { CHIP, CHIP_NUMBER } from '~/components/ui/classes'
import StepperField from '~/features/training/components/StepperField.vue'
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
    class="flex flex-col gap-3"
    novalidate
    @submit.prevent="submit"
  >
    <fieldset class="m-0 min-w-0 p-0">
      <legend class="mb-1 text-sm font-semibold">
        Type de série
      </legend>
      <div class="flex flex-wrap gap-2">
        <label
          v-for="option in kinds"
          :key="option.value"
          :class="CHIP"
        >
          <input
            v-model="kind"
            type="radio"
            :name="kindName"
            :value="option.value"
            class="sr-only"
          >
          <span>{{ option.label }}</span>
        </label>
        <button
          v-if="!moreKinds"
          type="button"
          class="text-accent-ink"
          :class="CHIP"
          @click="moreKinds = true"
        >
          Autre…
        </button>
      </div>
      <p class="mt-1 text-sm text-muted">
        {{ kindHint }}
      </p>
    </fieldset>

    <StepperField
      v-if="!timed"
      ref="loadField"
      v-model="load"
      :label="bodyweight ? 'Lest' : 'Charge'"
      suffix="kg"
      :hint="bodyweight ? 'Facultatif.' : undefined"
      :error="errors.load"
      :less-label="`Retirer ${kg(exercise.incrementKg)} kg`"
      :more-label="`Ajouter ${kg(exercise.incrementKg)} kg`"
      @less="step('load', -exercise.incrementKg)"
      @more="step('load', exercise.incrementKg)"
    />
    <StepperField
      v-if="timed"
      ref="countField"
      v-model="seconds"
      label="Durée"
      inputmode="numeric"
      suffix="s"
      :error="errors.seconds"
      less-label="Retirer 5 secondes"
      more-label="Ajouter 5 secondes"
      @less="step('seconds', -5, 1)"
      @more="step('seconds', 5, 1)"
    />
    <StepperField
      v-else
      ref="countField"
      v-model="reps"
      label="Répétitions"
      inputmode="numeric"
      :error="errors.reps"
      less-label="Une répétition de moins"
      more-label="Une répétition de plus"
      @less="step('reps', -1)"
      @more="step('reps', 1)"
    />
    <p
      class="sr-only"
      role="status"
    >
      {{ stepped }}
    </p>

    <fieldset class="m-0 min-w-0 p-0">
      <legend class="mb-1 text-sm font-semibold">
        RIR <span class="font-normal text-muted">(reps en réserve, 0 = échec, facultatif)</span>
      </legend>
      <div class="flex flex-wrap gap-2">
        <label
          v-for="value in RIR_VALUES"
          :key="value"
          :class="CHIP_NUMBER"
        >
          <input
            type="radio"
            :name="rirName"
            :value="value"
            :checked="rir === value"
            class="sr-only"
            @click="toggleRir(value)"
          >
          <span>{{ value }}</span>
        </label>
      </div>
    </fieldset>

    <div class="flex flex-col gap-2">
      <!-- The one button of the screen that matters: taller, larger text. -->
      <UiButton
        type="submit"
        class="min-h-14! text-lg"
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
