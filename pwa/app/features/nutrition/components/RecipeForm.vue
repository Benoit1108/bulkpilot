<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next'
import FoodPicker from '~/features/nutrition/components/FoodPicker.vue'
import { parseAmount, wholeAmount, type Amount } from '~/services/text/numbers'
import type { Loggable, Recipe, RecipeInput } from '~/features/nutrition/nutrition'

const MAX_INGREDIENTS = 50 // Recipe::MAX_INGREDIENTS

const props = defineProps<{ recipe?: Recipe | null }>()
const emit = defineEmits<{ saved: [recipe: Recipe] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const ingredientsError = useId()

const name = ref(props.recipe?.name ?? '')
const servings = ref<Amount>(props.recipe?.servings ?? 4)
const cookedWeight = ref<Amount>(props.recipe?.cookedWeightGrams ?? '')
let nextRow = 0
const ingredients = ref<Array<{ row: number, foodId: string, name: string, grams: Amount, available: boolean }>>(
  props.recipe?.ingredients.map(ingredient => ({ row: nextRow++, foodId: ingredient.foodId, name: ingredient.name, grams: ingredient.grams, available: ingredient.available })) ?? [],
)
const picking = ref(false)
const addButton = ref<{ $el: HTMLElement } | null>(null)

function addIngredient(item: Loggable): void {
  if (item.kind !== 'food') return
  ingredients.value.push({ row: nextRow++, foodId: item.food.id, name: item.food.name, grams: 100, available: true })
  picking.value = false
}

function removeIngredient(index: number): void {
  ingredients.value.splice(index, 1)
  // The removed row had the focus.
  void nextTick(() => addButton.value?.$el.focus())
}

/** Blank values are left out, so the API names the field instead of saving 0. */
function body(): RecipeInput {
  const cooked = parseAmount(cookedWeight.value)
  return {
    name: name.value,
    servings: wholeAmount(servings.value),
    cookedWeightGrams: cooked,
    ingredients: ingredients.value.map(ingredient => ({ foodId: ingredient.foodId, grams: parseAmount(ingredient.grams) ?? undefined })),
  } as RecipeInput
}

const { markSaved } = useUnsavedChanges(body)

async function submit(): Promise<void> {
  await run(async () => {
    const sent = body()
    const saved = props.recipe
      ? await api.put('/api/recipes/{id}', sent, { path: { id: props.recipe.id } })
      : await api.post('/api/recipes', sent)
    markSaved(sent)
    emit('saved', saved)
  })
}
</script>

<template>
  <div>
    <form
      class="flex flex-col gap-3"
      novalidate
      @submit.prevent="submit"
    >
      <UiAlert
        v-if="message"
        tone="error"
      >
        {{ message }}
      </UiAlert>
      <UiInput
        v-model="name"
        label="Nom de la recette"
        required
        :maxlength="200"
        :error="fields.name"
      />
      <div class="grid gap-3 sm:grid-cols-2">
        <UiInput
          v-model="servings"
          label="Nombre de portions"
          type="number"
          inputmode="numeric"
          :error="fields.servings"
        />
        <UiInput
          v-model="cookedWeight"
          label="Poids une fois cuit"
          type="number"
          suffix="g"
          hint="Facultatif&nbsp;: pèse le plat fini pour des portions exactes."
          :error="fields.cookedWeightGrams"
        />
      </div>

      <!-- A fieldset defaults to min-width: min-content and is a poor flex container: keep it a plain block. -->
      <fieldset
        class="m-0 min-w-0 p-0"
        :aria-describedby="fields.ingredients ? ingredientsError : undefined"
      >
        <legend class="mb-2 font-bold">
          Ingrédients
        </legend>
        <p
          v-if="fields.ingredients"
          :id="ingredientsError"
          class="text-sm text-danger"
        >
          {{ fields.ingredients }}
        </p>
        <div class="flex flex-col gap-3">
          <div
            v-for="(ingredient, index) in ingredients"
            :key="ingredient.row"
            class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2"
          >
            <UiInput
              v-model="ingredient.grams"
              :label="ingredient.available ? ingredient.name : `${ingredient.name} (aliment supprimé)`"
              type="number"
              suffix="g"
              :error="fields[`ingredients[${index}].grams`]"
            />
            <UiButton
              variant="ghost"
              :aria-label="`Retirer ${ingredient.name}`"
              @click="removeIngredient(index)"
            >
              <Trash2
                :size="18"
                aria-hidden="true"
              />
            </UiButton>
          </div>
          <UiButton
            ref="addButton"
            variant="secondary"
            :disabled="ingredients.length >= MAX_INGREDIENTS"
            @click="picking = true"
          >
            Ajouter un ingrédient
          </UiButton>
        </div>
      </fieldset>

      <UiButton
        type="submit"
        :loading="pending"
      >
        {{ recipe ? 'Enregistrer' : 'Créer la recette' }}
      </UiButton>
    </form>

    <!-- Outside the form: Enter in the ingredient search must not submit the recipe. -->
    <UiDrawer
      v-model:open="picking"
      title="Ajouter un ingrédient"
    >
      <FoodPicker
        :recipes="false"
        @pick="addIngredient"
      />
    </UiDrawer>
  </div>
</template>
