<script setup lang="ts">
import { EQUIPMENT, EXERCISE_TYPES, MEASUREMENTS, MUSCLES, type Equipment, type Exercise, type ExerciseInput, type ExerciseType, type Measurement, type Muscle } from '~/features/training/training'
import { parseAmount, wholeAmount, type Amount } from '~/services/text/numbers'

/**
 * One of the user's own exercises, with what it is usually done with (proposed when it is added
 * to a program).
 */
const props = defineProps<{ exercise?: Exercise | null }>()
const emit = defineEmits<{ saved: [exercise: Exercise] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()

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
    repMin: wholeAmount(repMin.value),
    repMax: wholeAmount(repMax.value),
    targetRir: rir === null ? null : Math.round(rir),
    restSeconds: wholeAmount(restSeconds.value),
    tempo: tempo.value.trim() || null,
    description: (description.value ?? '').trim() || null,
    mediaUrl: mediaUrl.value.trim() || null,
  } as ExerciseInput
}

const { markSaved } = useUnsavedChanges(body)

async function submit(): Promise<void> {
  await run(async () => {
    const sent = body()
    const saved = exercise
      ? await api.put('/api/exercises/{id}', sent, { path: { id: exercise.id } })
      : await api.post('/api/exercises', sent)
    markSaved(sent)
    emit('saved', saved)
  })
}
</script>

<template>
  <form
    class="flex flex-col gap-4"
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
      class="m-0 min-w-0 p-0"
      :aria-describedby="fields.secondaryMuscles ? secondaryError : undefined"
    >
      <legend class="mb-2 font-bold">
        Muscles secondaires
      </legend>
      <p
        v-if="fields.secondaryMuscles"
        :id="secondaryError"
        class="text-sm text-danger"
      >
        {{ fields.secondaryMuscles }}
      </p>
      <div class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-x-3">
        <UiCheckbox
          v-for="muscle in others"
          :key="muscle.value"
          :model-value="secondaryMuscles.includes(muscle.value)"
          :label="muscle.label"
          @update:model-value="toggle(muscle.value, $event)"
        />
      </div>
    </fieldset>
    <div class="grid grid-cols-2 gap-3">
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

    <fieldset class="m-0 min-w-0 p-0">
      <legend class="mb-2 font-bold">
        Habituellement
      </legend>
      <div class="grid grid-cols-2 gap-3">
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
        class="mt-3"
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
