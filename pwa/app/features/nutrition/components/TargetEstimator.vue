<script setup lang="ts">
import type { Account } from '~/features/identity/account'
import { HEIGHT_CM } from '~/features/identity/rules'
import EstimateBreakdown from '~/features/nutrition/components/EstimateBreakdown.vue'
import { localDay } from '~/features/nutrition/days'
import { formatKcal } from '~/features/nutrition/format'
import { parseAmount } from '~/services/text/numbers'
import { GOALS, LIFESTYLES, type EnergyEstimate, type EstimateInput, type Goal, type Lifestyle } from '~/features/nutrition/nutrition'
import { SESSIONS_PER_WEEK, TARGET_KCAL, TARGET_TOLERANCE, WEEKLY_CHANGE_KG, WEIGHT_KG, energyOfMacros } from '~/features/nutrition/rules'

/**
 * Step 1: the estimate (nothing saved). Step 2: the targets, prefilled from it and adjustable,
 * saved from today. Typing targets directly skips step 1.
 */
const props = defineProps<{ account: Account | null | undefined }>()
const emit = defineEmits<{ saved: [] }>()

const api = useApi()
const estimateTitle = useId()
const targetsTitle = useId()

type Amount = number | string | null
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

const estimation = useSubmission()
const saving = useSubmission()
const estimate = ref<EnergyEstimate | null>(null)
const estimatedFrom = ref<EstimateInput | null>(null)
const announcement = ref('')
const saved = ref(false)

const energyKcal = ref<Amount>('')
const protein = ref<Amount>('')
const carbohydrate = ref<Amount>('')
const fat = ref<Amount>('')

/** Blank or unreadable values are left out: the API then says which field is missing, never "0". */
function amount(value: Amount, round = false): number | undefined {
  const parsed = parseAmount(value)
  return parsed === null ? undefined : round ? Math.round(parsed) : parsed
}

function request(): EstimateInput {
  const change = goal.value === 'maintain' ? 0 : Math.abs(amount(speed.value) ?? 0)
  return {
    sex: sex.value ?? 'male',
    birthDate: birthDate.value,
    heightCm: amount(heightCm.value, true),
    weightKg: amount(weightKg.value),
    lifestyle: lifestyle.value ?? 'light',
    trainingSessionsPerWeek: amount(sessions.value, true) ?? 0,
    goal: goal.value ?? 'maintain',
    weeklyWeightChangeKg: goal.value === 'cut' ? -change : change,
  } as EstimateInput
}

async function calculate(): Promise<void> {
  const input = request()
  announcement.value = ''
  await estimation.run(async () => {
    const result = await api.post('/api/nutrition/targets/estimate', input)
    estimate.value = result
    estimatedFrom.value = input
    energyKcal.value = result.energyKcal
    protein.value = result.proteinGrams
    carbohydrate.value = result.carbohydrateGrams
    fat.value = result.fatGrams
    announcement.value = `Estimation : ${formatKcal(result.energyKcal)}, objectifs préremplis ci-dessous.`
  })
}

const macroEnergy = computed(() => energyOfMacros(amount(protein.value) ?? 0, amount(carbohydrate.value) ?? 0, amount(fat.value) ?? 0))
const target = computed(() => amount(energyKcal.value) ?? 0)
const consistent = computed(() => Math.abs(macroEnergy.value - target.value) <= target.value * TARGET_TOLERANCE)
const hasTargets = computed(() => target.value > 0)

async function save(): Promise<void> {
  saved.value = false
  const ok = await saving.run(async () => {
    await api.post('/api/nutrition/targets', {
      effectiveFrom: localDay(),
      energyKcal: amount(energyKcal.value, true),
      proteinGrams: amount(protein.value, true),
      carbohydrateGrams: amount(carbohydrate.value, true),
      fatGrams: amount(fat.value, true),
      estimate: estimatedFrom.value,
    } as Parameters<typeof api.post<'/api/nutrition/targets'>>[1])
  })
  if (ok) {
    saved.value = true
    emit('saved')
  }
}
</script>

<template>
  <div class="estimator">
    <form
      class="form"
      novalidate
      :aria-labelledby="estimateTitle"
      @submit.prevent="calculate"
    >
      <h2
        :id="estimateTitle"
        class="form__title"
      >
        Estimer mes besoins
      </h2>
      <UiAlert
        v-if="estimation.message.value"
        tone="error"
      >
        {{ estimation.message.value }}
      </UiAlert>
      <div class="form__grid">
        <UiSelect
          v-model="sex"
          label="Sexe"
          :options="sexOptions"
          :error="estimation.fields.value.sex"
        />
        <UiInput
          v-model="birthDate"
          label="Date de naissance"
          type="date"
          :error="estimation.fields.value.birthDate"
        />
        <UiInput
          v-model="heightCm"
          label="Taille"
          type="number"
          inputmode="numeric"
          suffix="cm"
          :hint="`Entre ${HEIGHT_CM.min} et ${HEIGHT_CM.max} cm.`"
          :error="estimation.fields.value.heightCm"
        />
        <UiInput
          v-model="weightKg"
          label="Poids actuel"
          type="number"
          suffix="kg"
          :hint="`Entre ${WEIGHT_KG.min} et ${WEIGHT_KG.max} kg.`"
          :error="estimation.fields.value.weightKg"
        />
        <UiSelect
          v-model="lifestyle"
          label="Mode de vie (hors sport)"
          :nullable="false"
          :options="lifestyleOptions"
          :error="estimation.fields.value.lifestyle"
        />
        <UiInput
          v-model="sessions"
          label="Séances par semaine"
          type="number"
          inputmode="numeric"
          :hint="`De ${SESSIONS_PER_WEEK.min} à ${SESSIONS_PER_WEEK.max}.`"
          :error="estimation.fields.value.trainingSessionsPerWeek"
        />
        <UiSelect
          v-model="goal"
          label="Objectif"
          :nullable="false"
          :options="GOALS"
          :error="estimation.fields.value.goal"
        />
        <UiInput
          v-if="goal !== 'maintain'"
          v-model="speed"
          :label="goal === 'cut' ? 'Perte visée par semaine' : 'Prise visée par semaine'"
          type="number"
          suffix="kg"
          :hint="`Entre ${WEEKLY_CHANGE_KG.min.toLocaleString('fr-FR')} et ${WEEKLY_CHANGE_KG.max} kg. De 0,25 à 0,5 kg limite la prise de gras.`"
          :error="estimation.fields.value.weeklyWeightChangeKg"
        />
      </div>
      <UiButton
        type="submit"
        variant="secondary"
        :loading="estimation.pending.value"
      >
        Calculer
      </UiButton>
      <p
        class="visually-hidden"
        role="status"
      >
        {{ announcement }}
      </p>
    </form>

    <EstimateBreakdown
      v-if="estimate"
      :estimate="estimate"
    />

    <form
      class="form"
      novalidate
      :aria-labelledby="targetsTitle"
      @submit.prevent="save"
    >
      <h2
        :id="targetsTitle"
        class="form__title"
      >
        Mes objectifs quotidiens
      </h2>
      <p class="form__intro">
        {{ estimate ? 'Ajuste si besoin, puis enregistre.' : 'Calcule une estimation ou saisis tes propres valeurs.' }}
        Ils s’appliquent à partir d’aujourd’hui&nbsp;; les jours passés gardent leurs objectifs.
      </p>
      <UiAlert
        v-if="saving.message.value"
        tone="error"
      >
        {{ saving.message.value }}
      </UiAlert>
      <div class="form__grid">
        <UiInput
          v-model="energyKcal"
          label="Calories"
          type="number"
          inputmode="numeric"
          suffix="kcal"
          :hint="`Entre ${formatKcal(TARGET_KCAL.min)} et ${formatKcal(TARGET_KCAL.max)}.`"
          :error="saving.fields.value.energyKcal"
        />
        <UiInput
          v-model="protein"
          label="Protéines"
          type="number"
          inputmode="numeric"
          suffix="g"
          :error="saving.fields.value.proteinGrams"
        />
        <UiInput
          v-model="carbohydrate"
          label="Glucides"
          type="number"
          inputmode="numeric"
          suffix="g"
          :error="saving.fields.value.carbohydrateGrams"
        />
        <UiInput
          v-model="fat"
          label="Lipides"
          type="number"
          inputmode="numeric"
          suffix="g"
          :error="saving.fields.value.fatGrams"
        />
      </div>
      <p
        v-if="hasTargets"
        class="form__check"
        :class="{ 'form__check--off': !consistent }"
        data-testid="macro-energy"
      >
        Les macros font {{ formatKcal(macroEnergy) }}{{ consistent ? '.' : ', trop loin des calories visées.' }}
      </p>
      <UiButton
        type="submit"
        :loading="saving.pending.value"
        :disabled="!hasTargets"
      >
        Enregistrer mes objectifs
      </UiButton>
      <UiAlert
        v-if="saved"
        tone="success"
      >
        Objectifs enregistrés. Ils s’appliquent dès aujourd’hui.
      </UiAlert>
    </form>
  </div>
</template>

<style scoped>
.estimator {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.form__title {
  font-size: var(--text-lg);
  font-weight: 700;
}

.form__intro,
.form__check {
  color: var(--color-text-muted);
}

.form__check--off {
  color: var(--color-danger);
}

.form__grid {
  display: grid;
  gap: var(--space-3);
}

@media (min-width: 40rem) {
  .form__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
