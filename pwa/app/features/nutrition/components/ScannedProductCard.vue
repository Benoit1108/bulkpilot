<script setup lang="ts">
import { formatKcal } from '~/features/nutrition/format'
import type { ScannedProduct } from '~/features/nutrition/nutrition'

/** What Open Food Facts proposes for a scanned code, per 100 g, with what is missing or looks wrong. */
const props = defineProps<{ product: ScannedProduct, barcode: string }>()

const headingId = useId()
const heading = ref<HTMLElement | null>(null)
defineExpose({ focus: () => heading.value?.focus() })

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

const per100g = computed(() => {
  const { product } = props
  const suspect = product.suspect === 'macros' ? ['protein', 'carbohydrate', 'fat'] : [product.suspect]
  return ([
    ['energyKcal', 'Énergie', product.energyKcal === null ? null : formatKcal(product.energyKcal)],
    ['protein', 'Protéines', product.protein === null ? null : grams(product.protein)],
    ['carbohydrate', 'Glucides', product.carbohydrate === null ? null : grams(product.carbohydrate)],
    ['fat', 'Lipides', product.fat === null ? null : grams(product.fat)],
  ] as const).map(([key, label, value]) => ({ key, label, value, suspect: suspect.includes(key) }))
})
</script>

<template>
  <article
    class="flex flex-col gap-2 rounded-md border border-line bg-surface-raised p-3"
    :aria-labelledby="headingId"
  >
    <h3
      :id="headingId"
      ref="heading"
      class="font-bold focus:outline-none"
      tabindex="-1"
    >
      {{ product.name ?? 'Produit sans nom' }}
    </h3>
    <p class="text-muted">
      {{ [product.brand, product.quantity].filter(Boolean).join(' · ') }}
      <span class="block text-sm tabular-nums">Code-barres&nbsp;: {{ barcode }}</span>
    </p>
    <p class="text-sm font-semibold">
      Pour 100&nbsp;g
      <template v-if="product.servingGrams">
        · portion indiquée&nbsp;: {{ grams(product.servingGrams) }}
      </template>
    </p>
    <dl class="grid grid-cols-2 gap-2">
      <div
        v-for="row in per100g"
        :key="row.key"
      >
        <dt class="text-sm text-muted">
          {{ row.label }}
        </dt>
        <dd
          class="font-semibold tabular-nums"
          :class="{ 'text-danger': row.value === null || row.suspect }"
        >
          {{ row.value ?? 'inconnu' }}
          <span
            v-if="row.suspect"
            class="text-sm font-normal"
          >(à vérifier)</span>
        </dd>
      </div>
    </dl>
    <p class="text-sm text-muted">
      Données Open Food Facts (ODbL), saisies par des contributeurs&nbsp;: compare-les avec l’étiquette.
    </p>
  </article>

  <UiAlert
    v-if="!product.complete"
    tone="info"
  >
    <template v-if="product.missing.length">
      Il manque des valeurs&nbsp;: complète-les depuis l’étiquette.
    </template>
    <template v-else-if="product.suspect">
      Ces valeurs ne tiennent pas ensemble ({{ suspectReason(product.suspect) }})&nbsp;: corrige-les depuis l’étiquette.
    </template>
  </UiAlert>
</template>
