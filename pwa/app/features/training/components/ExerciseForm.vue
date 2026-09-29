<script setup lang="ts">
import { EQUIPMENT, EXERCISE_TYPES, MEASUREMENTS, MUSCLES, type Equipment, type Exercise, type ExerciseInput, type ExerciseType, type Measurement, type Muscle } from '~/features/training/training'
import { parseAmount } from '~/services/text/numbers'

/**
 * One of the user's own exercises, with what it is usually done with (proposed when it is added
 * to a program).
 */
const props = defineProps<{ exercise?: Exercise | null }>()
const emit = defineEmits<{ saved: [exercise: Exercise] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()

type Amount = number | string | null
const exercise = props.exercise
const name = ref(exercise?.name ?? '')
const primaryMuscle = ref<Muscle | null>(exercise?.primaryMuscle ?? null)
const secondaryMuscles = ref<Muscle[]>([...(exercise?.secondaryMuscles ?? [])])
const type = ref<ExerciseType | null>(exercise?.type ?? 'compound')
const equipment = ref<Equipment | null>(exercise?.equipment ?? 'barbell')
const measurement = ref<Measurement | null>(exercise?.measurement ?? 'weight')
const repMin = ref<Amount>(exercise?.repMin ?? 8)
const repMax = ref<Amount>(exercise?.repMax ?? 12)
const targetRir = ref<Amount>(exercise?.targetRir ?? 2)
const restSeconds = ref<Amount>(exercise?.restSeconds ?? 120)
const tempo = ref(exercise?.tempo ?? '')
const description = ref<string | null>(exercise?.description ?? '')
const mediaUrl = ref(exercise?.mediaUrl ?? '')
const timed = computed(() => measurement.value === 'duration')
const secondaryError = useId()

const others = computed(() => MUSCLES.filter(muscle => muscle.value !== primaryMuscle.value))

function toggle(muscle: Muscle, on: boolean): void {
  secondaryMuscles.value = on ? [...secondaryMuscles.value, muscle] : secondaryMuscles.value.filter(value => value !== muscle)
}

function whole(value: Amount): number | undefined {
  const amount = parseAmount(value)
  return amount === null ? undefined : Math.round(amount)
}

/** Blank required values are left out, so the API names the field instead of saving 0. */
function body(): ExerciseInput {
  const rir = parseAmount(targetRir.value)
  return {
    name: name.value,
    primaryMuscle: primaryMuscle.value ?? undefined,
    secondaryMuscles: secondaryMuscles.value.filter(muscle => muscle !== primaryMuscle.value),
    type: type.value ?? 'compound',
    equipment: equipment.value ?? 'other',
    measurement: measurement.value ?? 'weight',
    repMin: whole(repMin.value),
    repMax: whole(repMax.value),
    targetRir: rir === null ? null : Math.round(rir),
    restSeconds: whole(restSeconds.value),
    tempo: tempo.value.trim() || null,
    description: (description.value ?? '').trim() || null,
    mediaUrl: mediaUrl.value.trim() || null,
  } as ExerciseInput
}

async function submit(): Promise<void> {
  await run(async () => {
    const saved = exercise
      ? await api.put('/api/exercises/{id}', body(), { path: { id: exercise.id } })
      : await api.post('/api/exercises', body())
    emit('saved', saved)
  })
}
</script>

<template>
  <form
    class="form"
    novalidate
    @submit.prevent="submit"
  >
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiInput
      v-model="name"
      label="Nom"
      required
      :maxlength="100"
      :error="fields.name"
    />
    <UiSelect
      v-model="primaryMuscle"
      label="Muscle principal"
      placeholder="Choisir"
      :options="MUSCLES"
      :error="fields.primaryMuscle"
    />
    <fieldset
      class="form__group"
      :aria-describedby="fields.secondaryMuscles ? secondaryError : undefined"
    >
      <legend>Muscles secondaires</legend>
      <p
        v-if="fields.secondaryMuscles"
        :id="secondaryError"
        class="form__error"
      >
        {{ fields.secondaryMuscles }}
      </p>
      <div class="form__checks">
        <UiCheckbox
          v-for="muscle in others"
          :key="muscle.value"
          :model-value="secondaryMuscles.includes(muscle.value)"
          :label="muscle.label"
          @update:model-value="toggle(muscle.value, $event)"
        />
      </div>
    </fieldset>
    <div class="form__grid">
      <UiSelect
        v-model="type"
        label="Type"
        :nullable="false"
        :options="EXERCISE_TYPES"
        :error="fields.type"
      />
      <UiSelect
        v-model="equipment"
        label="Équipement"
        :nullable="false"
        :options="EQUIPMENT"
        :error="fields.equipment"
      />
    </div>
    <UiSelect
      v-model="measurement"
      label="Ce qu’une série enregistre"
      :nullable="false"
      :options="MEASUREMENTS"
      :error="fields.measurement"
    />

    <fieldset class="form__group">
      <legend>Habituellement</legend>
      <div class="form__grid">
        <UiInput
          v-model="repMin"
          :label="timed ? 'Durée min.' : 'Reps min.'"
          type="number"
          inputmode="numeric"
          :suffix="timed ? 's' : undefined"
          :error="fields.repMin"
        />
        <UiInput
          v-model="repMax"
          :label="timed ? 'Durée max.' : 'Reps max.'"
          type="number"
          inputmode="numeric"
          :suffix="timed ? 's' : undefined"
          :error="fields.repMax"
        />
        <UiInput
          v-model="targetRir"
          label="RIR visé"
          type="number"
          inputmode="numeric"
          hint="Reps en réserve (0 = échec). Facultatif."
          :error="fields.targetRir"
        />
        <UiInput
          v-model="restSeconds"
          label="Repos"
          type="number"
          inputmode="numeric"
          suffix="s"
          :error="fields.restSeconds"
        />
      </div>
      <UiInput
        v-model="tempo"
        label="Tempo"
        literal
        :maxlength="15"
        hint="Facultatif&nbsp;: descente, pause, montée, pause, en secondes (3-1-1-0)."
        :error="fields.tempo"
      />
    </fieldset>

    <UiTextarea
      v-model="description"
      label="Description"
      :maxlength="2000"
      hint="Facultatif&nbsp;: réglages, placement, points clés."
      :error="fields.description"
    />
    <UiInput
      v-model="mediaUrl"
      label="Lien vers une vidéo ou une image"
      inputmode="text"
      literal
      :maxlength="500"
      hint="Facultatif, adresse en https."
      :error="fields.mediaUrl"
    />

    <UiButton
      type="submit"
      :loading="pending"
    >
      {{ exercise ? 'Enregistrer' : 'Créer l’exercice' }}
    </UiButton>
  </form>
</template>

<style scoped>
.form__group {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.form__group legend {
  margin-bottom: var(--space-2);
  font-weight: 700;
}

.form__error {
  color: var(--color-danger);
  font-size: var(--text-sm);
}

.form__checks {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
  column-gap: var(--space-3);
}

.form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.form__group > .ui-input {
  margin-top: var(--space-3);
}
</style>
