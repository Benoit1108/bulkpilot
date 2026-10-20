<script setup lang="ts">
import { QUICK_ADD, TEXT_LINK } from '~/components/ui/classes'
import { Check, Plus, ScanBarcode } from 'lucide-vue-next'
import FavoriteMealList from '~/features/nutrition/components/FavoriteMealList.vue'
import MacroLine from '~/features/nutrition/components/MacroLine.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { foodRow, recipeRow, type PickerRow, type PickerTab } from '~/features/nutrition/pickerRows'
import type { FavoriteMeal, Food, Loggable, Recipe } from '~/features/nutrition/nutrition'
import { matches } from '~/services/text/search'
import { SEARCH_MIN_LENGTH } from '~/features/nutrition/rules'

/**
 * Finds what to log: before anything is typed, recent foods, favourite meals and recipes, one tab
 * each; then as-you-type search over CIQUAL, the user's own foods and recipes. Recipes and
 * favourites can be left out (ingredients). With `quick`, a known quantity (a recent food's last
 * one, a recipe's serving) is added in one tap; touching the row sets the quantity first. While
 * one is on its way (`busy`), the « + » are dimmed and ignore taps; `justAdded` is the line whose
 * « + » shows a tick for a moment.
 */
const props = withDefaults(defineProps<{ recipes?: boolean, favorites?: boolean, scan?: boolean, quick?: boolean, busy?: boolean, justAdded?: string | null, initialQuery?: string }>(), { recipes: true, favorites: false, scan: false, quick: false, busy: false, justAdded: null, initialQuery: '' })
const emit = defineEmits<{ pick: [item: Loggable], quick: [item: Loggable, grams: number, amount: string], favorite: [meal: FavoriteMeal], scan: [] }>()

const api = useApi()
// A search found elsewhere (the global palette) starts here.
const query = ref(props.initialQuery)
const searched = ref('')
const results = ref<Food[]>([])
const searching = ref(false)
const failed = ref(false)

const { data: recent } = useAsyncData(NUTRITION_KEYS.recentFoods, () => api.get('/api/foods/recent'))
// Only loaded when shown (props are fixed for the picker's life), under the keys the other pages share.
const recipes = props.recipes ? useAsyncData(NUTRITION_KEYS.recipes, () => api.get('/api/recipes')).data : ref<Recipe[]>([])
const favoriteMeals = props.favorites ? useAsyncData(NUTRITION_KEYS.favoriteMeals, () => api.get('/api/nutrition/favorite-meals')).data : ref<FavoriteMeal[]>([])

type Tab = PickerTab
const tabs = computed(() => [
  { value: 'recent' as const, label: 'Récents' },
  ...(props.favorites ? [{ value: 'favorites' as const, label: 'Favoris' }] : []),
  ...(props.recipes ? [{ value: 'recipes' as const, label: 'Recettes' }] : []),
])
/** Kept by the drawer: coming back from a quantity, the user is on the tab they left. */
const tab = defineModel<Tab>('tab', { default: 'recent' })

const trimmed = computed(() => query.value.trim())
const browsing = computed(() => trimmed.value.length < SEARCH_MIN_LENGTH)
const shows = (shown: Tab) => !browsing.value || tab.value === shown

const foodRows = computed(() => (browsing.value ? recent.value ?? [] : results.value).map(foodRow))
const recipeRows = computed(() => (recipes.value ?? [])
  .filter(recipe => browsing.value || matches(recipe.name, trimmed.value))
  .map(recipeRow))
/** Found foods and recipes are titled; while browsing, the tab names the one list shown. */
const sections = computed(() => [
  { id: 'recent' as const, label: browsing.value ? 'Récents' : 'Aliments', rows: foodRows.value },
  { id: 'recipes' as const, label: 'Mes recettes', rows: recipeRows.value },
].filter(section => shows(section.id) && section.rows.length)
  .map(section => ({ ...section, titled: !browsing.value || tabs.value.length === 1 })))
const listedFavorites = computed(() => (browsing.value ? favoriteMeals.value ?? [] : []))
const quickLabel = (row: PickerRow) => `Ajouter ${row.name}, ${row.amount}`
const hasQuick = computed(() => props.quick && sections.value.some(section => section.rows.some(row => row.grams !== null)))
function quickAdd(row: PickerRow): void {
  if (!props.busy && row.grams !== null) emit('quick', row.item, row.grams, row.amount)
}

const found = computed(() => results.value.length + recipeRows.value.length)
// "Aliment introuvable ? Créer un aliment" brings the user back here once it is created.
const route = useRoute()
const createLink = computed(() => ({ path: '/nutrition/aliments/nouveau', query: { retour: route.fullPath } }))
const nothingFound = computed(() => !browsing.value && searched.value !== '' && !results.value.length && !recipeRows.value.length)
/** An empty tab says how it fills up. */
const emptyTab = computed(() => {
  if (!browsing.value) return null
  if (tab.value === 'recent' && recent.value && !recent.value.length) return 'Aucun aliment récent. Ceux que tu ajoutes au journal reviennent ici.'
  if (tab.value === 'favorites' && favoriteMeals.value && !favoriteMeals.value.length) return 'Aucun repas favori. Depuis le journal, un repas complet s’enregistre en favori.'
  if (tab.value === 'recipes' && recipes.value && !recipes.value.length) return 'Aucune recette. Compose-en une dans Mes recettes.'
  return null
})

let timer: ReturnType<typeof setTimeout> | undefined
let latest = 0

watch(trimmed, (value) => {
  clearTimeout(timer)
  if (value.length < SEARCH_MIN_LENGTH) {
    results.value = []
    searched.value = ''
    return
  }
  timer = setTimeout(() => search(value), 250)
}, { immediate: true }) // A search asked from elsewhere (the palette) runs as soon as the picker opens.

onBeforeUnmount(() => clearTimeout(timer))

async function search(value: string): Promise<void> {
  const call = ++latest
  searching.value = true
  failed.value = false
  try {
    const found = await api.get('/api/foods', { query: { q: value } })
    // A slower, older answer must not replace the one for what the user typed last.
    if (call === latest) {
      results.value = found
      searched.value = value
    }
  }
  catch {
    if (call === latest) failed.value = true
  }
  finally {
    if (call === latest) searching.value = false
  }
}

const ROW = 'flex min-h-tap min-w-0 flex-1 cursor-pointer flex-col items-start gap-1 border-0 bg-transparent px-0 py-2 text-left text-inherit'
</script>

<template>
  <div class="flex flex-col gap-3">
    <UiInput
      v-model="query"
      :label="recipes ? 'Rechercher un aliment ou une recette' : 'Rechercher un aliment'"
      type="text"
      autocomplete="off"
      hint="Par exemple «&nbsp;poulet cuit&nbsp;» ou «&nbsp;flocons avoine&nbsp;»."
      autofocus
    >
      <template
        v-if="scan"
        #end
      >
        <button
          type="button"
          class="grid size-tap shrink-0 cursor-pointer place-items-center border-0 bg-transparent p-0 text-accent-ink focus-visible:rounded-sm focus-visible:-outline-offset-4"
          aria-label="Scanner un code-barres"
          @click="emit('scan')"
        >
          <ScanBarcode
            :size="20"
            aria-hidden="true"
          />
        </button>
      </template>
    </UiInput>

    <UiSegmented
      v-if="browsing && tabs.length > 1"
      v-model="tab"
      legend="Afficher"
      hide-legend
      name="picker-tab"
      :options="tabs"
    />

    <p
      class="text-sm text-muted empty:hidden"
      role="status"
      data-testid="search-status"
    >
      <template v-if="searching">
        Recherche…
      </template>
      <template v-else-if="failed">
        La recherche a échoué. Vérifie ta connexion.
      </template>
      <template v-else-if="nothingFound">
        Aucun résultat pour «&nbsp;{{ searched }}&nbsp;».
      </template>
      <template v-else-if="!browsing && searched">
        {{ found }}&nbsp;résultat{{ found > 1 ? 's' : '' }}
      </template>
    </p>
    <p
      v-if="emptyTab"
      class="text-sm text-muted"
    >
      {{ emptyTab }}
    </p>

    <FavoriteMealList
      v-if="shows('favorites') && listedFavorites.length"
      :meals="listedFavorites"
      @pick="emit('favorite', $event)"
    />

    <section
      v-for="section in sections"
      :key="section.id"
      class="flex flex-col"
      :aria-label="section.label"
    >
      <h3
        v-if="section.titled"
        class="text-sm font-semibold text-muted"
      >
        {{ section.label }}
      </h3>
      <ul
        class="flex flex-col divide-y divide-line"
        role="list"
      >
        <li
          v-for="row in section.rows"
          :key="row.key"
          class="flex items-center gap-2"
        >
          <button
            type="button"
            :class="ROW"
            @click="emit('pick', row.item)"
          >
            <span class="font-semibold wrap-anywhere">{{ row.name }}</span>
            <span class="text-sm text-muted">{{ row.detail }}<template v-if="row.macros"> · <MacroLine
              :energy-kcal="row.macros.energyKcal"
              :protein="row.macros.protein"
              :carbohydrate="row.macros.carbohydrate"
              :fat="row.macros.fat"
            /></template></span>
            <span
              v-if="quick && row.grams !== null"
              class="sr-only"
            >, régler la quantité</span>
          </button>
          <button
            v-if="quick && row.grams !== null"
            type="button"
            :class="QUICK_ADD"
            :aria-label="quickLabel(row)"
            :aria-disabled="busy || undefined"
            @click="quickAdd(row)"
          >
            <Check
              v-if="justAdded === row.key"
              :size="20"
              aria-hidden="true"
            />
            <Plus
              v-else
              :size="20"
              aria-hidden="true"
            />
          </button>
        </li>
      </ul>
    </section>

    <p
      v-if="hasQuick"
      class="text-sm text-muted"
    >
      Le «&nbsp;+&nbsp;» ajoute avec la dernière quantité saisie (une portion pour une recette). Toucher la ligne règle d’abord la quantité.
    </p>
    <NuxtLink
      :to="createLink"
      :class="TEXT_LINK"
    >
      Aliment introuvable&nbsp;? Créer un aliment
    </NuxtLink>
    <p class="text-xs text-muted">
      Valeurs nutritionnelles&nbsp;: Anses, table de composition Ciqual 2020 (Licence Ouverte)&nbsp;; produits&nbsp;: Open Food Facts (ODbL).
    </p>
  </div>
</template>
