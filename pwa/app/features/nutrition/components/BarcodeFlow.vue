<script setup lang="ts">
import BarcodeScanner from '~/features/nutrition/components/BarcodeScanner.vue'
import CustomFoodForm from '~/features/nutrition/components/CustomFoodForm.vue'
import ScannedProductCard from '~/features/nutrition/components/ScannedProductCard.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import type { BarcodeScan, CustomFoodInput, Food, ScannedProduct } from '~/features/nutrition/nutrition'
import { asApiError } from '~/services/api/client'
import type { ApiError } from '~/services/api/errors'
import { messageFor } from '~/services/api/messages'

/**
 * Spec §20: scan → Symfony → Open Food Facts → normalised values → the user confirms (or corrects)
 * → the food is saved, then logged by the caller. Nothing is kept without the user's say-so.
 */
const emit = defineEmits<{ food: [food: Food] }>()

const api = useApi()
/** Why the user types the values in: nobody knows the product, the database is down, or its values need fixing. */
type Reason = 'unknown' | 'unreachable' | 'incomplete' | 'correcting'
type Step
  = | { kind: 'scanning' }
    | { kind: 'looking', barcode: string }
    | { kind: 'proposed', barcode: string, product: ScannedProduct }
    | { kind: 'completing', barcode: string, product: ScannedProduct | null, reason: Reason }
    | { kind: 'failed', barcode: string, error: ApiError }
const step = ref<Step>({ kind: 'scanning' })
const confirming = useSubmission()

/** Each step starts at its heading, so that keyboard and screen reader users follow along. */
const heading = ref<{ focus: () => void } | null>(null)
watch(() => step.value.kind, () => {
  confirming.clear()
  void nextTick(() => {
    // The form focuses its first value to check itself.
    if (step.value.kind === 'completing' && step.value.product) return
    heading.value?.focus()
  })
}, { flush: 'post' })
onMounted(() => heading.value?.focus())

/** A late answer to a cancelled lookup is ignored. */
let lookup = 0

async function lookUp(barcode: string): Promise<void> {
  const current = ++lookup
  step.value = { kind: 'looking', barcode }
  try {
    const scan: BarcodeScan = await api.post('/api/nutrition/barcode/scan', { barcode })
    if (current !== lookup) return
    if (scan.status === 'known' && scan.food) {
      emit('food', scan.food)
      return
    }
    step.value = scan.product
      ? { kind: 'proposed', barcode: scan.barcode, product: scan.product }
      : { kind: 'completing', barcode: scan.barcode, product: null, reason: 'unknown' }
  }
  catch (caught) {
    if (current !== lookup) return
    const error = asApiError(caught)
    step.value = error.code === 'nutrition.product_not_found'
      ? { kind: 'completing', barcode, product: null, reason: 'unknown' }
      : { kind: 'failed', barcode, error }
  }
}

function scanAgain(): void {
  lookup++
  step.value = { kind: 'scanning' }
}

async function confirm(barcode: string): Promise<void> {
  await confirming.run(async () => {
    emit('food', await api.post('/api/nutrition/barcode/confirm', { barcode }))
  })
}

function saveCorrected(barcode: string) {
  return (corrections: CustomFoodInput) => api.post('/api/nutrition/barcode/confirm', { barcode, corrections })
}

function saved(food: Food): void {
  clearNuxtData(NUTRITION_KEYS.customFoods)
  emit('food', food)
}

const INTROS: Record<Reason, string> = {
  unknown: 'Produit inconnu d’Open Food Facts\u00A0: saisis-le depuis son étiquette, tu le retrouveras en le scannant.',
  unreachable: 'Sans Open Food Facts pour l’instant, saisis le produit depuis son étiquette\u00A0: tu le retrouveras en le scannant.',
  incomplete: 'Complète les valeurs depuis l’étiquette. Le produit sera enregistré comme ton aliment\u00A0; les autres gardent celui d’Open Food Facts.',
  correcting: 'Corrige les valeurs d’après l’étiquette. La version corrigée sera ton aliment\u00A0; les autres gardent celle d’Open Food Facts.',
}

/** One live region for the whole flow: steps come and go, the announcement stays put. */
const announcement = computed(() => {
  switch (step.value.kind) {
    case 'looking': return `Recherche du produit ${step.value.barcode}…`
    case 'proposed': return `Produit trouvé\u00A0: ${step.value.product.name ?? 'sans nom'}.`
    case 'completing': return INTROS[step.value.reason]
    default: return ''
  }
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <p
      class="sr-only"
      role="status"
      data-testid="scan-status"
    >
      {{ announcement }}
    </p>

    <template v-if="step.kind === 'scanning'">
      <h3
        ref="heading"
        class="font-bold focus:outline-none"
        tabindex="-1"
      >
        Scanner le code-barres
      </h3>
      <BarcodeScanner @code="lookUp" />
    </template>

    <template v-else-if="step.kind === 'looking'">
      <h3
        ref="heading"
        class="font-bold focus:outline-none"
        tabindex="-1"
      >
        Recherche du produit
      </h3>
      <p
        class="flex items-center gap-2 text-muted"
        aria-hidden="true"
      >
        <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent motion-reduce:animate-none" />
        Code-barres&nbsp;: {{ step.barcode }}
      </p>
      <UiButton
        variant="secondary"
        @click="scanAgain"
      >
        Annuler
      </UiButton>
    </template>

    <template v-else-if="step.kind === 'proposed'">
      <ScannedProductCard
        ref="heading"
        :product="step.product"
        :barcode="step.barcode"
      />
      <UiAlert
        v-if="confirming.message.value"
        tone="error"
      >
        {{ confirming.message.value }}
      </UiAlert>
      <div class="flex flex-wrap gap-2">
        <UiButton
          v-if="step.product.complete"
          :loading="confirming.pending.value"
          @click="confirm(step.barcode)"
        >
          Valeurs conformes à l’étiquette
        </UiButton>
        <UiButton
          :variant="step.product.complete ? 'secondary' : 'primary'"
          :disabled="confirming.pending.value"
          @click="step = { kind: 'completing', barcode: step.barcode, product: step.product, reason: step.product.complete ? 'correcting' : 'incomplete' }"
        >
          {{ step.product.complete ? 'Corriger pour moi' : 'Compléter les valeurs' }}
        </UiButton>
      </div>
      <p
        v-if="step.product.complete"
        class="text-sm text-muted"
      >
        Conformes, elles rejoignent le catalogue commun. Corrigées, elles ne valent que pour toi.
      </p>
    </template>

    <template v-else-if="step.kind === 'completing'">
      <h3
        ref="heading"
        class="font-bold focus:outline-none"
        tabindex="-1"
      >
        {{ step.reason === 'correcting' ? 'Corriger les valeurs' : 'Saisir le produit' }}
      </h3>
      <p class="text-muted">
        {{ INTROS[step.reason] }}
        <span class="block text-sm tabular-nums">Code-barres&nbsp;: {{ step.barcode }}</span>
      </p>
      <CustomFoodForm
        :draft="{ ...(step.product ?? {}), barcode: step.barcode }"
        :save="saveCorrected(step.barcode)"
        field-prefix="corrections."
        submit-label="Enregistrer ce produit"
        @saved="saved"
      />
    </template>

    <template v-else-if="step.kind === 'failed'">
      <h3
        ref="heading"
        class="font-bold focus:outline-none"
        tabindex="-1"
      >
        Recherche impossible
      </h3>
      <UiAlert tone="error">
        {{ messageFor(step.error) }}
      </UiAlert>
      <div class="flex flex-wrap gap-2">
        <UiButton @click="lookUp(step.barcode)">
          Réessayer
        </UiButton>
        <UiButton
          variant="secondary"
          @click="step = { kind: 'completing', barcode: step.barcode, product: null, reason: 'unreachable' }"
        >
          Saisir le produit
        </UiButton>
      </div>
    </template>

    <UiButton
      v-if="step.kind !== 'scanning' && step.kind !== 'looking' && !confirming.pending.value"
      variant="ghost"
      @click="scanAgain"
    >
      Scanner un autre produit
    </UiButton>
  </div>
</template>
