<script setup lang="ts">
import BarcodeFlow from '~/features/nutrition/components/BarcodeFlow.vue'
import FoodPicker from '~/features/nutrition/components/FoodPicker.vue'
import QuantityForm from '~/features/nutrition/components/QuantityForm.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatGrams } from '~/features/nutrition/format'
import { toSlot, type FavoriteMeal, type Loggable, type LogSource, type MealSlot } from '~/features/nutrition/nutrition'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{
  mealSlot: MealSlot
  log: (source: LogSource, slot: MealSlot, grams: number) => Promise<void>
  logFavorite: (favoriteId: string, slot: MealSlot) => Promise<void>
  /** What to search for first (a food found with the global search). */
  initialQuery?: string
}>()
/** What was added and where, for the confirmation shown once the drawer is closed. */
const emit = defineEmits<{ done: [message: string, slot: MealSlot] }>()

const picked = ref<Loggable | null>(null)
const scanning = ref(false)
const { pending, message, fields, run, clear } = useSubmission()

watch(open, (value) => {
  if (!value) {
    picked.value = null
    scanning.value = false
  }
})
// An error belongs to the step that produced it.
watch(picked, clear)

/** A recipe is logged by weight of the dish; one serving is its natural portion. */
const quantity = computed(() => {
  const item = picked.value
  if (!item) return null
  if (item.kind === 'food') {
    return { name: item.food.name, per100g: item.food.per100g, portions: item.food.portions, grams: item.food.portions[0]?.grams ?? 100, hint: undefined }
  }
  const serving = item.recipe.servingGrams
  return {
    name: item.recipe.name,
    per100g: item.recipe.per100g,
    portions: [{ label: '1 portion', grams: serving }, { label: '2 portions', grams: serving * 2 }],
    grams: serving,
    hint: `Une portion pèse ${formatGrams(serving)}.`,
  }
})

function done(text: string, slot: MealSlot): void {
  open.value = false
  clearNuxtData(NUTRITION_KEYS.recentFoods)
  emit('done', text, slot)
}

async function add(grams: number, slot: MealSlot): Promise<void> {
  const item = picked.value
  if (!item) return
  const source: LogSource = item.kind === 'food' ? { foodId: item.food.id } : { recipeId: item.recipe.id }
  const name = item.kind === 'food' ? item.food.name : item.recipe.name
  if (await run(() => props.log(source, slot, grams))) done(`${name} ajouté ${toSlot(slot)}.`, slot)
}

async function addFavorite(meal: FavoriteMeal): Promise<void> {
  const slot = props.mealSlot
  if (await run(() => props.logFavorite(meal.id, slot))) {
    const count = meal.items.length
    done(`« ${meal.name} » ajouté ${toSlot(slot)} (${count} élément${count > 1 ? 's' : ''}).`, slot)
  }
}
</script>

<template>
  <UiDrawer
    v-model:open="open"
    :title="`Ajouter ${toSlot(mealSlot)}`"
  >
    <template v-if="!quantity && scanning">
      <BarcodeFlow @food="picked = { kind: 'food', food: $event }" />
      <UiButton
        variant="ghost"
        @click="scanning = false"
      >
        Rechercher par le nom
      </UiButton>
    </template>
    <template v-else-if="!quantity">
      <UiAlert
        v-if="message"
        tone="error"
      >
        {{ message }}
      </UiAlert>
      <FoodPicker
        favorites
        scan
        :initial-query="initialQuery"
        @pick="picked = $event"
        @favorite="addFavorite"
        @scan="scanning = true"
      />
    </template>
    <QuantityForm
      v-else
      :name="quantity.name"
      :per100g="quantity.per100g"
      :portions="quantity.portions"
      :hint="quantity.hint"
      :initial-grams="quantity.grams"
      :initial-slot="mealSlot"
      submit-label="Ajouter"
      :pending="pending"
      :error="message"
      :fields="fields"
      @submit="add"
    >
      <UiButton
        variant="ghost"
        @click="picked = null; scanning = false"
      >
        Choisir autre chose
      </UiButton>
    </QuantityForm>
  </UiDrawer>
</template>
