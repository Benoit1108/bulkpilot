<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next'
import { parseAmount } from '~/features/nutrition/format'
import { FOOD_STATES, type CustomFoodInput, type Food, type FoodState } from '~/features/nutrition/nutrition'
import { MAX_PORTIONS } from '~/features/nutrition/rules'

/** Values per 100 g, as printed on the label. */
const props = defineProps<{ food?: Food | null }>()
const emit = defineEmits<{ saved: [food: Food] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()

type Amount = number | string | null
type Nutrient = 'energyKcal' | 'protein' | 'carbohydrate' | 'sugars' | 'fat' | 'saturatedFat' | 'fibre' | 'salt'

/** In the order of a nutrition label; the first four are required. */
const NUTRIENTS: ReadonlyArray<{ key: Nutrient, label: string, suffix: string, required: boolean }> = [
  { key: 'energyKcal', label: 'Énergie', suffix: 'kcal', required: true },
  { key: 'protein', label: 'Protéines', suffix: 'g', required: true },
  { key: 'carbohydrate', label: 'Glucides', suffix: 'g', required: true },
  { key: 'sugars', label: 'dont sucres', suffix: 'g', required: false },
  { key: 'fat', label: 'Lipides', suffix: 'g', required: true },
  { key: 'saturatedFat', label: 'dont saturés', suffix: 'g', required: false },
  { key: 'fibre', label: 'Fibres', suffix: 'g', required: false },
  { key: 'salt', label: 'Sel', suffix: 'g', required: false },
]

const name = ref(props.food?.name ?? '')
const brand = ref(props.food?.brand ?? '')
const state = ref<FoodState | null>(props.food?.state ?? 'not_applicable')
const values = reactive(Object.fromEntries(NUTRIENTS.map(nutrient => [nutrient.key, props.food?.per100g[nutrient.key] ?? ''])) as Record<Nutrient, Amount>)
const portions = ref<Array<{ id: number, label: string, grams: Amount }>>(props.food?.portions.map((portion, index) => ({ id: index, ...portion })) ?? [])
let nextPortion = portions.value.length

function addPortion(): void {
  portions.value.push({ id: nextPortion++, label: '', grams: '' })
}

const addPortionButton = ref<{ $el: HTMLElement } | null>(null)
function removePortion(index: number): void {
  portions.value.splice(index, 1)
  // The removed row had the focus.
  void nextTick(() => addPortionButton.value?.$el.focus())
}

/**
 * A blank required value is left out, so the API names the missing field; a blank optional one
 * is null (unknown).
 */
function body(): CustomFoodInput {
  const nutrients = Object.fromEntries(NUTRIENTS.flatMap((nutrient) => {
    const amount = parseAmount(values[nutrient.key])
    return amount === null && nutrient.required ? [] : [[nutrient.key, amount]]
  }))
  return {
    name: name.value,
    brand: brand.value || null,
    state: state.value ?? 'not_applicable',
    ...nutrients,
    portions: portions.value.map(portion => ({ label: portion.label, grams: parseAmount(portion.grams) ?? undefined })),
  } as CustomFoodInput
}

async function submit(): Promise<void> {
  await run(async () => {
    const saved = props.food
      ? await api.put('/api/foods/{id}', body(), { path: { id: props.food.id } })
      : await api.post('/api/foods', body())
    emit('saved', saved)
  })
}
</script>

<template>
  <form
    class="form"
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
      label="Nom"
      required
      :maxlength="200"
      :error="fields.name"
    />
    <UiInput
      v-model="brand"
      label="Marque"
      :maxlength="100"
      :error="fields.brand"
    />
    <UiSelect
      v-model="state"
      label="État"
      :nullable="false"
      :options="FOOD_STATES"
      :error="fields.state"
    />

    <fieldset class="form__group">
      <legend>Pour 100&nbsp;g</legend>
      <div class="form__grid">
        <UiInput
          v-for="nutrient in NUTRIENTS"
          :key="nutrient.key"
          v-model="values[nutrient.key]"
          :label="nutrient.label"
          type="number"
          :suffix="nutrient.suffix"
          :required="nutrient.required"
          :error="fields[nutrient.key]"
        />
      </div>
    </fieldset>

    <fieldset class="form__group">
      <legend>Portions</legend>
      <div class="form__group-body">
        <p class="form__hint">
          Par exemple «&nbsp;1 pot&nbsp;» = 150&nbsp;g, pour saisir plus vite.
        </p>
        <div
          v-for="(portion, index) in portions"
          :key="portion.id"
          class="portion"
        >
          <UiInput
            v-model="portion.label"
            :label="`Nom de la portion ${index + 1}`"
            :maxlength="40"
            :error="fields[`portions[${index}].label`]"
          />
          <UiInput
            v-model="portion.grams"
            :label="`Poids de la portion ${index + 1}`"
            type="number"
            suffix="g"
            :error="fields[`portions[${index}].grams`]"
          />
          <UiButton
            variant="ghost"
            :aria-label="`Retirer la portion ${index + 1}`"
            @click="removePortion(index)"
          >
            <Trash2
              :size="18"
              aria-hidden="true"
            />
          </UiButton>
        </div>
        <UiButton
          ref="addPortionButton"
          variant="secondary"
          :disabled="portions.length >= MAX_PORTIONS"
          @click="addPortion"
        >
          Ajouter une portion
        </UiButton>
      </div>
    </fieldset>

    <UiButton
      type="submit"
      :loading="pending"
    >
      {{ food ? 'Enregistrer' : 'Créer l’aliment' }}
    </UiButton>
  </form>
</template>

<style scoped>
.form {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

/* A fieldset defaults to min-width: min-content and is a poor flex container: keep it a plain block. */
.form__group {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.form__group legend {
  margin-bottom: var(--space-2);
  font-weight: 700;
}

.form__group-body {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.form__hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
}

.portion {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
  align-items: end;
  gap: var(--space-2);
}
</style>
