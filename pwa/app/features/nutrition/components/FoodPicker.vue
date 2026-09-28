<script setup lang="ts">
import { ScanBarcode } from 'lucide-vue-next'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal } from '~/features/nutrition/format'
import { matches, type FavoriteMeal, type Food, type Loggable, type Recipe } from '~/features/nutrition/nutrition'
import { SEARCH_MIN_LENGTH } from '~/features/nutrition/rules'

/**
 * Finds what to log: favourite meals and recent foods first, then as-you-type search over
 * CIQUAL, the user's own foods and recipes. Recipes and favourites can be left out (ingredients).
 */
const props = withDefaults(defineProps<{ recipes?: boolean, favorites?: boolean, scan?: boolean }>(), { recipes: true, favorites: false, scan: false })
const emit = defineEmits<{ pick: [item: Loggable], favorite: [meal: FavoriteMeal], scan: [] }>()

const api = useApi()
const query = ref('')
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
})

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

function foodDetail(food: Food): string {
  return [food.brand, `${formatKcal(food.per100g.energyKcal)} / 100\u00A0g`].filter(Boolean).join(' · ')
}
</script>

<template>
  <div class="picker">
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
      class="picker__status"
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
      class="picker__group"
      aria-label="Repas favoris"
    >
      <h3 class="picker__heading">
        Repas favoris
      </h3>
      <ul class="picker__list">
        <li
          v-for="meal in listedFavorites"
          :key="meal.id"
        >
          <button
            type="button"
            class="item"
            @click="emit('favorite', meal)"
          >
            <span class="item__name"><span class="visually-hidden">Ajouter le repas favori </span>{{ meal.name }}</span>
            <span class="item__detail">{{ meal.items.map(item => item.name).join(', ') }} · {{ formatKcal(meal.totals.energyKcal) }}</span>
          </button>
        </li>
      </ul>
    </section>

    <section
      v-if="foods.length"
      class="picker__group"
      :aria-label="browsing ? 'Récents' : 'Aliments'"
    >
      <h3 class="picker__heading">
        {{ browsing ? 'Récents' : 'Aliments' }}
      </h3>
      <ul class="picker__list">
        <li
          v-for="food in foods"
          :key="food.id"
        >
          <button
            type="button"
            class="item"
            @click="emit('pick', { kind: 'food', food })"
          >
            <span class="item__name">{{ food.name }}</span>
            <span class="item__detail">{{ foodDetail(food) }}</span>
          </button>
        </li>
      </ul>
    </section>

    <section
      v-if="listedRecipes.length"
      class="picker__group"
      aria-label="Mes recettes"
    >
      <h3 class="picker__heading">
        Mes recettes
      </h3>
      <ul class="picker__list">
        <li
          v-for="recipe in listedRecipes"
          :key="recipe.id"
        >
          <button
            type="button"
            class="item"
            @click="emit('pick', { kind: 'recipe', recipe })"
          >
            <span class="item__name">{{ recipe.name }}</span>
            <span class="item__detail">{{ formatKcal(recipe.perServing.energyKcal) }} / portion</span>
          </button>
        </li>
      </ul>
    </section>

    <NuxtLink
      :to="createLink"
      class="picker__create"
    >
      Aliment introuvable&nbsp;? Créer un aliment
    </NuxtLink>
    <p class="picker__source">
      Valeurs nutritionnelles&nbsp;: Anses, table de composition Ciqual 2020 (Licence Ouverte)&nbsp;; produits&nbsp;: Open Food Facts (ODbL).
    </p>
  </div>
</template>

<style scoped>
.picker {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.picker__status {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.picker__status:empty {
  display: none;
}

.picker__group {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.picker__heading {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-text-muted);
}

.picker__list {
  display: flex;
  flex-direction: column;
}

.item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-1);
  width: 100%;
  min-height: var(--tap-target);
  padding: var(--space-2) 0;
  background: transparent;
  border: 0;
  border-top: 1px solid var(--color-border);
  color: inherit;
  text-align: left;
  cursor: pointer;
}

.item__name {
  font-weight: 600;
}

.item__detail {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.picker__create {
  display: inline-flex;
  align-items: center;
  min-height: var(--tap-target);
  color: var(--color-accent-text);
  font-weight: 600;
}

.picker__source {
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}
</style>
