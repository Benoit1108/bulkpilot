<script setup lang="ts">
import { ScanBarcode, Trash2 } from 'lucide-vue-next'
import BarcodeScanner from '~/features/nutrition/components/BarcodeScanner.vue'
import { parseAmount } from '~/services/text/numbers'
import { FOOD_STATES, type CustomFoodInput, type Food, type FoodState, type ScannedProduct } from '~/features/nutrition/nutrition'
import { MAX_PORTIONS } from '~/features/nutrition/rules'

/**
 * Values per 100 g, as printed on the label. Starts from an existing food (editing), from what a
 * scan proposed (`draft`, with its barcode), or empty. `save` replaces the default create/update;
 * `fieldPrefix` is where its payload sits in the request (violations come back under that path).
 */
const props = withDefaults(defineProps<{
  food?: Food | null
  draft?: (Partial<ScannedProduct> & { barcode: string }) | null
  save?: ((body: CustomFoodInput) => Promise<Food>) | null
  submitLabel?: string
  fieldPrefix?: string
}>(), { food: null, draft: null, save: null, submitLabel: undefined, fieldPrefix: '' })
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

const draft = props.draft
const name = ref(props.food?.name ?? draft?.name ?? '')
const brand = ref(props.food?.brand ?? draft?.brand ?? '')
const state = ref<FoodState | null>(props.food?.state ?? 'not_applicable')
const barcode = ref(props.food?.barcode ?? draft?.barcode ?? '')
const values = reactive(Object.fromEntries(NUTRIENTS.map(nutrient => [nutrient.key, props.food?.per100g[nutrient.key] ?? draft?.[nutrient.key] ?? ''])) as Record<Nutrient, Amount>)
const initialPortions = props.food?.portions ?? (draft?.servingGrams ? [{ label: '1 portion', grams: draft.servingGrams }] : [])
const portions = ref<Array<{ id: number, label: string, grams: Amount }>>(initialPortions.map((portion, index) => ({ id: index, ...portion })))
/** Values the scanned product did not give: the user is asked for them first. */
const missing = new Set<string>(draft?.missing ?? [])
/** Values that do not hold together with the others ("macros": protein, carbohydrate and fat). */
const suspect = new Set<string>(draft?.suspect === 'macros' ? ['protein', 'carbohydrate', 'fat'] : draft?.suspect ? [draft.suspect] : [])
/** The field to fill first: focused when the form opens from a scan. */
const firstToCheck = draft ? (['name', ...NUTRIENTS.map(nutrient => nutrient.key)].find(key => missing.has(key) || suspect.has(key)) ?? null) : null
let nextPortion = portions.value.length

function hintFor(key: string): string | undefined {
  if (missing.has(key)) return 'Absent d’Open Food Facts\u00A0: recopie-le de l’étiquette.'
  if (suspect.has(key)) return 'À vérifier sur l’étiquette.'
  return undefined
}

function fieldError(path: string): string | undefined {
  return fields.value[props.fieldPrefix + path]
}

const scanning = ref(false)
function scanned(code: string): void {
  barcode.value = code
  scanning.value = false
}

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
    barcode: barcode.value.trim() || null,
    ...nutrients,
    portions: portions.value.map(portion => ({ label: portion.label, grams: parseAmount(portion.grams) ?? undefined })),
  } as CustomFoodInput
}

const { markSaved } = useUnsavedChanges(body)

async function submit(): Promise<void> {
  await run(async () => {
    const sent = body()
    const saved = props.save
      ? await props.save(sent)
      : props.food
        ? await api.put('/api/foods/{id}', sent, { path: { id: props.food.id } })
        : await api.post('/api/foods', sent)
    markSaved(sent)
    emit('saved', saved)
  })
}
</script>

<template>
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
      label="Nom"
      required
      :maxlength="200"
      :autofocus="firstToCheck === 'name'"
      :hint="hintFor('name')"
      :error="fieldError('name')"
    />
    <UiInput
      v-model="brand"
      label="Marque"
      :maxlength="100"
      :error="fieldError('brand')"
    />
    <div
      v-if="!draft"
      class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-2"
    >
      <UiInput
        v-model="barcode"
        label="Code-barres"
        inputmode="numeric"
        literal
        :maxlength="20"
        hint="Facultatif&nbsp;: pour le retrouver en le scannant."
        :error="fieldError('barcode')"
      />
      <!-- Level with the field, below its label. -->
      <UiButton
        class="mt-label"
        variant="secondary"
        aria-label="Scanner le code-barres"
        @click="scanning = true"
      >
        <ScanBarcode
          :size="18"
          aria-hidden="true"
        />
      </UiButton>
      <!-- Outside the form: the scanner has its own (typed code). -->
      <Teleport to="body">
        <UiDrawer
          v-model:open="scanning"
          title="Scanner le code-barres"
        >
          <BarcodeScanner
            v-if="scanning"
            @code="scanned"
          />
        </UiDrawer>
      </Teleport>
    </div>
    <UiSelect
      v-model="state"
      label="État"
      :nullable="false"
      :options="FOOD_STATES"
      :error="fieldError('state')"
    />

    <!-- A fieldset defaults to min-width: min-content: min-w-0 keeps it a plain block. -->
    <fieldset class="m-0 min-w-0 p-0">
      <legend class="mb-2 font-bold">
        Pour 100&nbsp;g
      </legend>
      <div class="grid grid-cols-2 gap-3">
        <UiInput
          v-for="nutrient in NUTRIENTS"
          :key="nutrient.key"
          v-model="values[nutrient.key]"
          :label="nutrient.label"
          type="number"
          :suffix="nutrient.suffix"
          :required="nutrient.required"
          :autofocus="firstToCheck === nutrient.key"
          :hint="hintFor(nutrient.key)"
          :error="fieldError(nutrient.key)"
        />
      </div>
    </fieldset>

    <fieldset class="m-0 min-w-0 p-0">
      <legend class="mb-2 font-bold">
        Portions
      </legend>
      <div class="flex flex-col gap-3">
        <p class="text-sm text-muted">
          Par exemple «&nbsp;1 pot&nbsp;» = 150&nbsp;g, pour saisir plus vite.
        </p>
        <div
          v-for="(portion, index) in portions"
          :key="portion.id"
          class="grid grid-cols-[minmax(0,2fr)_minmax(0,1fr)_auto] items-end gap-2"
        >
          <UiInput
            v-model="portion.label"
            :label="`Nom de la portion ${index + 1}`"
            :maxlength="40"
            :error="fieldError(`portions[${index}].label`)"
          />
          <UiInput
            v-model="portion.grams"
            :label="`Poids de la portion ${index + 1}`"
            type="number"
            suffix="g"
            :error="fieldError(`portions[${index}].grams`)"
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
      {{ submitLabel ?? (food ? 'Enregistrer' : 'Créer l’aliment') }}
    </UiButton>
  </form>
</template>
