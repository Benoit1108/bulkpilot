<script setup lang="ts">
import type { Account } from '~/features/identity/account'
import { HEIGHT_CM } from '~/features/identity/rules'
import { optionalAmount, type Amount } from '~/services/text/numbers'
import { formatKcal } from '~/features/nutrition/format'
import { GOALS, LIFESTYLES, type EnergyEstimate, type EstimateInput, type Goal, type Lifestyle } from '~/features/nutrition/nutrition'
import { SESSIONS_PER_WEEK, WEEKLY_CHANGE_KG, WEIGHT_KG } from '~/features/nutrition/rules'

/** Step 1 of the targets: the estimate, nothing saved. Prefilled from the profile. */
const props = defineProps<{ account: Account | null | undefined }>()
const emit = defineEmits<{ estimated: [estimate: EnergyEstimate, input: EstimateInput] }>()

const api = useApi()
const titleId = useId()

const sex = ref<'female' | 'male' | null>(props.account?.sex ?? null)
const birthDate = ref(props.account?.birthDate ?? '')
const heightCm = ref<Amount>(props.account?.heightCm ?? '')
const weightKg = ref<Amount>('')
const lifestyle = ref<Lifestyle | null>('light')
const sessions = ref<Amount>(3)
const goal = ref<Goal | null>('bulk')
const speed = ref<Amount>('0,25')

const lifestyleOptions = LIFESTYLES.map(option => ({ value: option.value, label: `${option.label} (${option.hint.toLowerCase()})` }))
const sexOptions = [{ value: 'female', label: 'Femme' }, { value: 'male', label: 'Homme' }] as const

const { pending, message, fields, run } = useSubmission()
const announcement = ref('')

function request(): EstimateInput {
  const change = goal.value === 'maintain' ? 0 : Math.abs(optionalAmount(speed.value) ?? 0)
  return {
    sex: sex.value ?? 'male',
    birthDate: birthDate.value,
    heightCm: optionalAmount(heightCm.value, true),
    weightKg: optionalAmount(weightKg.value),
    lifestyle: lifestyle.value ?? 'light',
    trainingSessionsPerWeek: optionalAmount(sessions.value, true) ?? 0,
    goal: goal.value ?? 'maintain',
    weeklyWeightChangeKg: goal.value === 'cut' ? -change : change,
  } as EstimateInput
}

async function calculate(): Promise<void> {
  const input = request()
  announcement.value = ''
  await run(async () => {
    const result = await api.post('/api/nutrition/targets/estimate', input)
    emit('estimated', result, input)
    announcement.value = `Estimation : ${formatKcal(result.energyKcal)}, objectifs préremplis ci-dessous.`
  })
}
</script>

<template>
  <form
    class="flex flex-col gap-3"
    novalidate
    :aria-labelledby="titleId"
    @submit.prevent="calculate"
  >
    <h2
      :id="titleId"
      class="text-lg font-bold"
    >
      Estimer mes besoins
    </h2>
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <div class="grid gap-3 sm:grid-cols-2">
      <UiSelect
        v-model="sex"
        label="Sexe"
        :options="sexOptions"
        :error="fields.sex"
      />
      <UiInput
        v-model="birthDate"
        label="Date de naissance"
        type="date"
        :error="fields.birthDate"
      />
      <UiInput
        v-model="heightCm"
        label="Taille"
        type="number"
        inputmode="numeric"
        suffix="cm"
        :hint="`Entre ${HEIGHT_CM.min} et ${HEIGHT_CM.max} cm.`"
        :error="fields.heightCm"
      />
      <UiInput
        v-model="weightKg"
        label="Poids actuel"
        type="number"
        suffix="kg"
        :hint="`Entre ${WEIGHT_KG.min} et ${WEIGHT_KG.max} kg.`"
        :error="fields.weightKg"
      />
      <UiSelect
        v-model="lifestyle"
        label="Mode de vie (hors sport)"
        :nullable="false"
        :options="lifestyleOptions"
        :error="fields.lifestyle"
      />
      <UiInput
        v-model="sessions"
        label="Séances par semaine"
        type="number"
        inputmode="numeric"
        :hint="`De ${SESSIONS_PER_WEEK.min} à ${SESSIONS_PER_WEEK.max}.`"
        :error="fields.trainingSessionsPerWeek"
      />
      <UiSelect
        v-model="goal"
        label="Objectif"
        :nullable="false"
        :options="GOALS"
        :error="fields.goal"
      />
      <UiInput
        v-if="goal !== 'maintain'"
        v-model="speed"
        :label="goal === 'cut' ? 'Perte visée par semaine' : 'Prise visée par semaine'"
        type="number"
        suffix="kg"
        :hint="`Entre ${WEEKLY_CHANGE_KG.min.toLocaleString('fr-FR')} et ${WEEKLY_CHANGE_KG.max} kg. De 0,25 à 0,5 kg limite la prise de gras.`"
        :error="fields.weeklyWeightChangeKg"
      />
    </div>
    <UiButton
      type="submit"
      variant="secondary"
      :loading="pending"
    >
      Calculer
    </UiButton>
    <p
      class="sr-only"
      role="status"
    >
      {{ announcement }}
    </p>
  </form>
</template>
