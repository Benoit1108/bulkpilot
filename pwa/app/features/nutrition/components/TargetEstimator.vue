<script setup lang="ts">
import type { Account } from '~/features/identity/account'
import EstimateBreakdown from '~/features/nutrition/components/EstimateBreakdown.vue'
import EstimateForm from '~/features/nutrition/components/EstimateForm.vue'
import { optionalAmount, type Amount } from '~/services/text/numbers'
import { localDay } from '~/services/calendar/days'
import { formatKcal } from '~/features/nutrition/format'
import type { EnergyEstimate, EstimateInput } from '~/features/nutrition/nutrition'
import { TARGET_KCAL, TARGET_TOLERANCE, energyOfMacros } from '~/features/nutrition/rules'

/**
 * Step 1: the estimate (nothing saved). Step 2: the targets, prefilled from it and adjustable,
 * saved from today. Typing targets directly skips step 1.
 */
defineProps<{ account: Account | null | undefined }>()
const emit = defineEmits<{ saved: [] }>()

const api = useApi()
const targetsTitle = useId()

const saving = useSubmission()
const estimate = ref<EnergyEstimate | null>(null)
const estimatedFrom = ref<EstimateInput | null>(null)
const saved = ref(false)

const energyKcal = ref<Amount>('')
const protein = ref<Amount>('')
const carbohydrate = ref<Amount>('')
const fat = ref<Amount>('')

function prefill(result: EnergyEstimate, input: EstimateInput): void {
  estimate.value = result
  estimatedFrom.value = input
  energyKcal.value = result.energyKcal
  protein.value = result.proteinGrams
  carbohydrate.value = result.carbohydrateGrams
  fat.value = result.fatGrams
}

const macroEnergy = computed(() => energyOfMacros(optionalAmount(protein.value) ?? 0, optionalAmount(carbohydrate.value) ?? 0, optionalAmount(fat.value) ?? 0))
const target = computed(() => optionalAmount(energyKcal.value) ?? 0)
const consistent = computed(() => Math.abs(macroEnergy.value - target.value) <= target.value * TARGET_TOLERANCE)
const hasTargets = computed(() => target.value > 0)

/** The targets being typed (or prefilled from an estimate): leaving before saving them asks first. */
const typed = () => [energyKcal.value, protein.value, carbohydrate.value, fat.value]
const { markSaved } = useUnsavedChanges(typed)

async function save(): Promise<void> {
  saved.value = false
  const sent = typed()
  const ok = await saving.run(async () => {
    await api.post('/api/nutrition/targets', {
      effectiveFrom: localDay(),
      energyKcal: optionalAmount(energyKcal.value, true),
      proteinGrams: optionalAmount(protein.value, true),
      carbohydrateGrams: optionalAmount(carbohydrate.value, true),
      fatGrams: optionalAmount(fat.value, true),
      estimate: estimatedFrom.value,
    } as Parameters<typeof api.post<'/api/nutrition/targets'>>[1])
  })
  if (ok) {
    markSaved(sent)
    saved.value = true
    emit('saved')
  }
}
</script>

<template>
  <div class="flex flex-col gap-6">
    <EstimateForm
      :account="account"
      @estimated="prefill"
    />

    <EstimateBreakdown
      v-if="estimate"
      :estimate="estimate"
    />

    <form
      class="flex flex-col gap-3"
      novalidate
      :aria-labelledby="targetsTitle"
      @submit.prevent="save"
    >
      <h2
        :id="targetsTitle"
        class="text-lg font-bold"
      >
        Mes objectifs quotidiens
      </h2>
      <p class="text-muted">
        {{ estimate ? 'Ajuste si besoin, puis enregistre.' : 'Calcule une estimation ou saisis tes propres valeurs.' }}
        Ils s’appliquent à partir d’aujourd’hui&nbsp;; les jours passés gardent leurs objectifs.
      </p>
      <UiAlert
        v-if="saving.message.value"
        tone="error"
      >
        {{ saving.message.value }}
      </UiAlert>
      <div class="grid gap-3 sm:grid-cols-2">
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
        :class="consistent ? 'text-muted' : 'text-danger'"
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
