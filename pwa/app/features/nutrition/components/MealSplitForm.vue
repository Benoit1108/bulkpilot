<script setup lang="ts">
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { parseAmount } from '~/services/text/numbers'
import { MEAL_SLOTS, type MealSlot, type MealSplit } from '~/features/nutrition/nutrition'

const props = defineProps<{ split: MealSplit }>()
const emit = defineEmits<{ saved: [split: MealSplit] }>()

const api = useApi()
const { pending, message, run } = useSubmission()
const shares = reactive<Record<MealSlot, number | string | null>>({ ...props.split })

/** Whole percents, as they will be sent: the total shown is the one the server checks. */
const rounded = computed(() => Object.fromEntries(MEAL_SLOTS.map(slot => [slot.value, Math.round(parseAmount(shares[slot.value]) ?? 0)])) as MealSplit)
const total = computed(() => MEAL_SLOTS.reduce((sum, slot) => sum + rounded.value[slot.value], 0))
const gap = computed(() => (total.value < 100 ? `il manque ${100 - total.value}\u00A0%` : `${total.value - 100}\u00A0% de trop`))

const { markSaved } = useUnsavedChanges(() => rounded.value)

async function submit(): Promise<void> {
  await run(async () => {
    const sent = rounded.value
    const saved = await api.put('/api/nutrition/meal-split', sent)
    markSaved(sent)
    emit('saved', saved)
    clearNuxtData(NUTRITION_KEYS.mealSplit)
  })
}
</script>

<template>
  <form
    class="flex flex-col gap-3"
    novalidate
    @submit.prevent="submit"
  >
    <p class="text-muted">
      Part des calories du jour prévue pour chaque repas. Mets 0&nbsp;% à un repas que tu ne prends pas.
    </p>
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiInput
      v-for="slot in MEAL_SLOTS"
      :key="slot.value"
      v-model="shares[slot.value]"
      :label="slot.label"
      type="number"
      inputmode="numeric"
      suffix="%"
    />
    <p
      class="font-semibold tabular-nums"
      :class="{ 'text-danger': total !== 100 }"
      role="status"
      data-testid="split-total"
    >
      Total&nbsp;: {{ total }}&nbsp;%<template v-if="total !== 100">
        ({{ gap }})
      </template>
    </p>
    <UiButton
      type="submit"
      :loading="pending"
      :disabled="total !== 100"
    >
      Enregistrer
    </UiButton>
  </form>
</template>
