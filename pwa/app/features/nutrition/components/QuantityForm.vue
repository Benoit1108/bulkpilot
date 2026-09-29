<script setup lang="ts">
import MacroLine from '~/features/nutrition/components/MacroLine.vue'
import { formatGrams } from '~/features/nutrition/format'
import { parseAmount } from '~/services/text/numbers'
import { MEAL_SLOTS, type MealSlot } from '~/features/nutrition/nutrition'

/**
 * How much, and in which meal. The preview multiplies the per-100 g values for guidance only:
 * the journal shows the server's figures once saved.
 */
const props = withDefaults(defineProps<{
  name: string
  per100g: { energyKcal: number, protein: number, carbohydrate: number, fat: number }
  portions?: ReadonlyArray<{ label: string, grams: number }>
  /** Shown under the portions, e.g. what one serving of a recipe weighs. */
  hint?: string
  initialGrams: number
  initialSlot: MealSlot
  submitLabel: string
  pending?: boolean
  error?: string | null
  fields?: Record<string, string>
}>(), { portions: () => [], hint: undefined, pending: false, error: null, fields: () => ({}) })

const emit = defineEmits<{ submit: [grams: number, slot: MealSlot] }>()

const grams = ref<number | string | null>(props.initialGrams)
const slot = ref<MealSlot | null>(props.initialSlot)

const quantity = computed(() => {
  const value = parseAmount(grams.value)
  return value !== null && value > 0 ? value : 0
})
const preview = computed(() => {
  const factor = quantity.value / 100
  return {
    energyKcal: props.per100g.energyKcal * factor,
    protein: props.per100g.protein * factor,
    carbohydrate: props.per100g.carbohydrate * factor,
    fat: props.per100g.fat * factor,
  }
})

function submit(): void {
  if (quantity.value > 0) emit('submit', quantity.value, slot.value ?? props.initialSlot)
}
</script>

<template>
  <form
    class="flex flex-col gap-3"
    novalidate
    @submit.prevent="submit"
  >
    <p class="font-bold">
      {{ name }}
    </p>
    <UiAlert
      v-if="error"
      tone="error"
    >
      {{ error }}
    </UiAlert>

    <div
      v-if="portions.length"
      class="flex flex-wrap gap-2"
      role="group"
      aria-label="Portions"
    >
      <button
        v-for="portion in portions"
        :key="portion.label"
        type="button"
        class="min-h-tap cursor-pointer rounded-full border border-line bg-surface-raised px-3 text-inherit aria-pressed:border-accent aria-pressed:bg-accent-soft"
        :aria-pressed="quantity === portion.grams"
        @click="grams = portion.grams"
      >
        {{ portion.label }} ({{ formatGrams(portion.grams) }})
      </button>
    </div>
    <p
      v-if="hint"
      class="text-sm text-muted"
      data-testid="quantity-hint"
    >
      {{ hint }}
    </p>

    <UiInput
      v-model="grams"
      label="Quantité"
      type="number"
      suffix="g"
      required
      autofocus
      :error="fields.grams ?? (grams !== '' && grams !== null && quantity <= 0 ? 'Indique une quantité, par exemple 150 ou 12,5.' : undefined)"
    />
    <UiSelect
      v-model="slot"
      label="Repas"
      :options="MEAL_SLOTS"
      :nullable="false"
      :error="fields.slot"
    />

    <p
      class="text-muted"
      data-testid="quantity-preview"
    >
      <MacroLine
        :energy-kcal="preview.energyKcal"
        :protein="preview.protein"
        :carbohydrate="preview.carbohydrate"
        :fat="preview.fat"
      />
    </p>

    <UiButton
      type="submit"
      :loading="pending"
      :disabled="quantity <= 0"
    >
      {{ submitLabel }}
    </UiButton>
    <slot />
  </form>
</template>
