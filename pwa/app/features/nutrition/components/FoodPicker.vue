<script setup lang="ts">
import { LIST_ROW, TEXT_LINK } from '~/components/ui/classes'
import { ScanBarcode } from 'lucide-vue-next'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal } from '~/features/nutrition/format'
import type { FavoriteMeal, Food, Loggable, Recipe } from '~/features/nutrition/nutrition'
import { matches } from '~/services/text/search'
import { SEARCH_MIN_LENGTH } from '~/features/nutrition/rules'

/**
 * Finds what to log: favourite meals and recent foods first, then as-you-type search over
 * CIQUAL, the user's own foods and recipes. Recipes and favourites can be left out (ingredients).
 */
const props = withDefaults(defineProps<{ recipes?: boolean, favorites?: boolean, scan?: boolean, initialQuery?: string }>(), { recipes: true, favorites: false, scan: false, initialQuery: '' })
const emit = defineEmits<{ pick: [item: Loggable], favorite: [meal: FavoriteMeal], scan: [] }>()

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

const trimmed = computed(() => query.value.trim())
const browsing = computed(() => trimmed.value.length < SEARCH_MIN_LENGTH)
const foods = computed<Food[]>(() => (browsing.value ? recent.value ?? [] : results.value))
/** While browsing, a few recipes only: recent foods stay within reach above the keyboard. */
const BROWSED_RECIPES = 3
const listedRecipes = computed(() => {
  const all = recipes.value ?? []
  return browsing.value ? all.slice(0, BROWSED_RECIPES) : all.filter(recipe => matches(recipe.name, trimmed.value))
})
const found = computed(() => results.value.length + listedRecipes.value.length)
// "Aliment introuvable ? Créer un aliment" brings the user back here once it is created.
const route = useRoute()
const createLink = computed(() => ({ path: '/nutrition/aliments/nouveau', query: { retour: route.fullPath } }))
const listedFavorites = computed(() => (browsing.value ? favoriteMeals.value ?? [] : []))
const nothingFound = computed(() => !browsing.value && searched.value !== '' && !results.value.length && !listedRecipes.value.length)

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

/** One row of a result list: the whole row picks. */

function foodDetail(food: Food): string {
  return [food.brand, `${formatKcal(food.per100g.energyKcal)} / 100\u00A0g`].filter(Boolean).join(' · ')
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <UiButton
      v-if="scan"
      variant="secondary"
      @click="emit('scan')"
    >
      <ScanBarcode
        :size="18"
        aria-hidden="true"
      />
      Scanner un code-barres
    </UiButton>
    <UiInput
      v-model="query"
      :label="recipes ? 'Rechercher un aliment ou une recette' : 'Rechercher un aliment'"
      type="text"
      autocomplete="off"
      hint="Par exemple «&nbsp;poulet cuit&nbsp;» ou «&nbsp;flocons avoine&nbsp;»."
      autofocus
    />

    <p
      class="text-sm text-muted empty:hidden"
      role="status"
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

    <section
      v-if="listedFavorites.length"
      class="flex flex-col gap-1"
      aria-label="Repas favoris"
    >
      <h3 class="text-sm font-semibold text-muted">
        Repas favoris
      </h3>
      <ul class="flex flex-col">
        <li
          v-for="meal in listedFavorites"
          :key="meal.id"
        >
          <button
            type="button"
            :class="LIST_ROW"
            @click="emit('favorite', meal)"
          >
            <span class="font-semibold"><span class="sr-only">Ajouter le repas favori </span>{{ meal.name }}</span>
            <span class="text-sm text-muted">{{ meal.items.map(item => item.name).join(', ') }} · {{ formatKcal(meal.totals.energyKcal) }}</span>
          </button>
        </li>
      </ul>
    </section>

    <section
      v-if="foods.length"
      class="flex flex-col gap-1"
      :aria-label="browsing ? 'Récents' : 'Aliments'"
    >
      <h3 class="text-sm font-semibold text-muted">
        {{ browsing ? 'Récents' : 'Aliments' }}
      </h3>
      <ul class="flex flex-col">
        <li
          v-for="food in foods"
          :key="food.id"
        >
          <button
            type="button"
            :class="LIST_ROW"
            @click="emit('pick', { kind: 'food', food })"
          >
            <span class="font-semibold">{{ food.name }}</span>
            <span class="text-sm text-muted">{{ foodDetail(food) }}</span>
          </button>
        </li>
      </ul>
    </section>

    <section
      v-if="listedRecipes.length"
      class="flex flex-col gap-1"
      aria-label="Mes recettes"
    >
      <h3 class="text-sm font-semibold text-muted">
        Mes recettes
      </h3>
      <ul class="flex flex-col">
        <li
          v-for="recipe in listedRecipes"
          :key="recipe.id"
        >
          <button
            type="button"
            :class="LIST_ROW"
            @click="emit('pick', { kind: 'recipe', recipe })"
          >
            <span class="font-semibold">{{ recipe.name }}</span>
            <span class="text-sm text-muted">{{ formatKcal(recipe.perServing.energyKcal) }} / portion</span>
          </button>
        </li>
      </ul>
    </section>

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
