<script setup lang="ts">
import BarcodeScanner from '~/features/nutrition/components/BarcodeScanner.vue'
import CustomFoodForm from '~/features/nutrition/components/CustomFoodForm.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal } from '~/features/nutrition/format'
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
const productHeading = useId()

/** Each step starts at its heading, so that keyboard and screen reader users follow along. */
const heading = ref<HTMLElement | null>(null)
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

const grams = (value: number) => `${value.toLocaleString('fr-FR')}\u00A0g`
const NUTRIENT_NAMES: Record<string, string> = {
  energyKcal: 'l’énergie', protein: 'les protéines', carbohydrate: 'les glucides', fat: 'les lipides',
  fibre: 'les fibres', sugars: 'les sucres', saturatedFat: 'les acides gras saturés', salt: 'le sel',
}

/** What looks wrong, in words. */
function suspectReason(suspect: NonNullable<ScannedProduct['suspect']>): string {
  switch (suspect) {
    case 'macros': return 'protéines, glucides et lipides dépassent 100\u00A0g pour 100\u00A0g'
    case 'sugars': return 'plus de sucres que de glucides'
    case 'saturatedFat': return 'plus d’acides gras saturés que de lipides'
    default: return `${NUTRIENT_NAMES[suspect]} ne semble pas plausible`
  }
}

function per100g(product: ScannedProduct) {
  const suspect = product.suspect === 'macros' ? ['protein', 'carbohydrate', 'fat'] : [product.suspect]
  return ([
    ['energyKcal', 'Énergie', product.energyKcal === null ? null : formatKcal(product.energyKcal)],
    ['protein', 'Protéines', product.protein === null ? null : grams(product.protein)],
    ['carbohydrate', 'Glucides', product.carbohydrate === null ? null : grams(product.carbohydrate)],
    ['fat', 'Lipides', product.fat === null ? null : grams(product.fat)],
  ] as const).map(([key, label, value]) => ({ key, label, value, suspect: suspect.includes(key) }))
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
  <div class="flow">
    <p
      class="visually-hidden"
      role="status"
    >
      {{ announcement }}
    </p>

    <template v-if="step.kind === 'scanning'">
      <h3
        ref="heading"
        class="flow__title"
        tabindex="-1"
      >
        Scanner le code-barres
      </h3>
      <BarcodeScanner @code="lookUp" />
    </template>

    <template v-else-if="step.kind === 'looking'">
      <h3
        ref="heading"
        class="flow__title"
        tabindex="-1"
      >
        Recherche du produit
      </h3>
      <p
        class="muted flow__looking"
        aria-hidden="true"
      >
        <span class="flow__spinner" />
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
      <article
        class="product"
        :aria-labelledby="productHeading"
      >
        <h3
          :id="productHeading"
          ref="heading"
          class="product__name"
          tabindex="-1"
        >
          {{ step.product.name ?? 'Produit sans nom' }}
        </h3>
        <p class="muted">
          {{ [step.product.brand, step.product.quantity].filter(Boolean).join(' · ') }}
          <span class="product__code">Code-barres&nbsp;: {{ step.barcode }}</span>
        </p>
        <p class="product__caption">
          Pour 100&nbsp;g
          <template v-if="step.product.servingGrams">
            · portion indiquée&nbsp;: {{ grams(step.product.servingGrams) }}
          </template>
        </p>
        <dl class="product__values">
          <div
            v-for="row in per100g(step.product)"
            :key="row.key"
          >
            <dt>{{ row.label }}</dt>
            <dd :class="{ product__missing: row.value === null || row.suspect }">
              {{ row.value ?? 'inconnu' }}
              <span
                v-if="row.suspect"
                class="product__flag"
              >(à vérifier)</span>
            </dd>
          </div>
        </dl>
        <p class="muted product__note">
          Données Open Food Facts (ODbL), saisies par des contributeurs&nbsp;: compare-les avec l’étiquette.
        </p>
      </article>

      <UiAlert
        v-if="!step.product.complete"
        tone="info"
      >
        <template v-if="step.product.missing.length">
          Il manque des valeurs&nbsp;: complète-les depuis l’étiquette.
        </template>
        <template v-else-if="step.product.suspect">
          Ces valeurs ne tiennent pas ensemble ({{ suspectReason(step.product.suspect) }})&nbsp;: corrige-les depuis l’étiquette.
        </template>
      </UiAlert>
      <UiAlert
        v-if="confirming.message.value"
        tone="error"
      >
        {{ confirming.message.value }}
      </UiAlert>
      <div class="flow__actions">
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
        class="muted product__note"
      >
        Conformes, elles rejoignent le catalogue commun. Corrigées, elles ne valent que pour toi.
      </p>
    </template>

    <template v-else-if="step.kind === 'completing'">
      <h3
        ref="heading"
        class="flow__title"
        tabindex="-1"
      >
        {{ step.reason === 'correcting' ? 'Corriger les valeurs' : 'Saisir le produit' }}
      </h3>
      <p class="muted">
        {{ INTROS[step.reason] }}
        <span class="product__code">Code-barres&nbsp;: {{ step.barcode }}</span>
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
        class="flow__title"
        tabindex="-1"
      >
        Recherche impossible
      </h3>
      <UiAlert tone="error">
        {{ messageFor(step.error) }}
      </UiAlert>
      <div class="flow__actions">
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

<style scoped>
.flow {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.flow__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.flow__title {
  font-weight: 700;
}

.flow__title:focus,
.product__name:focus {
  outline: none;
}

.flow__looking {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.flow__spinner {
  width: 1rem;
  height: 1rem;
  border: 2px solid currentcolor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: flow-spin 0.8s linear infinite;
}

@keyframes flow-spin {
  to { transform: rotate(1turn); }
}

@media (prefers-reduced-motion: reduce) {
  .flow__spinner { animation: none; }
}

.muted {
  color: var(--color-text-muted);
}

.product {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.product__name {
  font-weight: 700;
}

.product__code {
  display: block;
  font-size: var(--text-sm);
  font-variant-numeric: tabular-nums;
}

.product__values {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2);
}

.product__values dt {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.product__values dd {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.product__missing {
  color: var(--color-danger);
}

.product__caption {
  font-size: var(--text-sm);
  font-weight: 600;
}

.product__flag {
  font-size: var(--text-sm);
  font-weight: 400;
}

.product__note {
  font-size: var(--text-sm);
}
</style>
