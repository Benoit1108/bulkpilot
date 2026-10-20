<script setup lang="ts">
import BarcodeFlow from '~/features/nutrition/components/BarcodeFlow.vue'
import FoodPicker from '~/features/nutrition/components/FoodPicker.vue'
import QuantityForm from '~/features/nutrition/components/QuantityForm.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatGrams } from '~/features/nutrition/format'
import type { PickerTab } from '~/features/nutrition/pickerRows'
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

const tab = ref<PickerTab>('recent')

/**
 * Quick adds keep the drawer open (a meal is several foods): each is said here and its « + »
 * turns to a tick for a moment; the journal sums them up once the drawer is closed.
 */
const quickAdded = ref<string[]>([])
const lastQuick = ref<string | null>(null)
const justAdded = ref<string | null>(null)
let tickTimer: ReturnType<typeof setTimeout> | undefined
onBeforeUnmount(() => clearTimeout(tickTimer))

function quickSummary(): string | null {
  const added = quickAdded.value
  if (added.length < 2) return added[0] ?? null
  return `${added.length}\u00A0aliments ajoutés ${toSlot(props.mealSlot)}.`
}

watch(open, (value) => {
  if (value) return
  const summary = quickSummary()
  picked.value = null
  scanning.value = false
  tab.value = 'recent'
  quickAdded.value = []
  lastQuick.value = null
  justAdded.value = null
  if (summary) {
    clearNuxtData(NUTRITION_KEYS.recentFoods)
    emit('done', summary, props.mealSlot)
  }
})
// An error belongs to the step that produced it.
watch(picked, clear)

/** A recipe is logged by weight of the dish; one serving is its natural portion. */
const quantity = computed(() => {
  const item = picked.value
  if (!item) return null
  if (item.kind === 'food') {
    // A recent food starts at the quantity its line shows.
    const grams = item.food.lastGrams ?? item.food.portions[0]?.grams ?? 100
    return { name: item.food.name, per100g: item.food.per100g, portions: item.food.portions, grams, hint: undefined }
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
  // The quick adds before it are said first, then this one.
  const before = quickSummary()
  quickAdded.value = []
  open.value = false
  clearNuxtData(NUTRITION_KEYS.recentFoods)
  emit('done', before ? `${before} ${text}` : text, slot)
}

async function add(grams: number, slot: MealSlot): Promise<void> {
  const item = picked.value
  if (!item) return
  if (await run(() => props.log(sourceOf(item), slot, grams))) done(`${nameOf(item)} ajouté ${toSlot(slot)}.`, slot)
}

const nameOf = (item: Loggable) => (item.kind === 'food' ? item.food.name : item.recipe.name)
const sourceOf = (item: Loggable): LogSource => (item.kind === 'food' ? { foodId: item.food.id } : { recipeId: item.recipe.id })

async function quickAdd(item: Loggable, grams: number, amount: string): Promise<void> {
  const slot = props.mealSlot
  if (!await run(() => props.log(sourceOf(item), slot, grams))) {
    lastQuick.value = null
    return
  }
  const text = `${nameOf(item)} ajouté ${toSlot(slot)} (${amount}).`
  // Closed while it was on its way: the journal says it at once.
  if (!open.value) {
    clearNuxtData(NUTRITION_KEYS.recentFoods)
    emit('done', text, slot)
    return
  }
  quickAdded.value = [...quickAdded.value, text]
  justAdded.value = item.kind === 'food' ? item.food.id : item.recipe.id
  clearTimeout(tickTimer)
  tickTimer = setTimeout(() => (justAdded.value = null), 2000)
  // The same food twice is announced twice: the message changes back and forth.
  lastQuick.value = null
  await nextTick()
  lastQuick.value = text
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
      <!-- In the sheet, not over it (a toast would hide the rows below). -->
      <p
        class="mb-3 text-sm font-semibold text-success empty:hidden"
        role="status"
        data-testid="quick-status"
      >
        {{ lastQuick }}
      </p>
      <FoodPicker
        v-model:tab="tab"
        favorites
        scan
        quick
        :busy="pending"
        :just-added="justAdded"
        :initial-query="initialQuery"
        @pick="picked = $event"
        @quick="quickAdd"
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
