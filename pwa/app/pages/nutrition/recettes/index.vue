<script setup lang="ts">
import { CookingPot } from 'lucide-vue-next'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal } from '~/features/nutrition/format'

definePageMeta({ title: 'Mes recettes' })

const api = useApi()
const { data: recipes, error, refresh } = useAsyncData(NUTRITION_KEYS.recipes, () => api.get('/api/recipes'))
const items = computed(() => (recipes.value ?? []).map(recipe => ({
  to: `/nutrition/recettes/${recipe.id}`,
  title: recipe.name,
  summary: `${recipe.servings} portion${recipe.servings > 1 ? 's' : ''} · ${formatKcal(recipe.perServing.energyKcal)} / portion`,
})))
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/nutrition"
      label="Nutrition"
    />
    <NuxtLink
      to="/nutrition/recettes/nouvelle"
      class="link-button"
    >
      Nouvelle recette
    </NuxtLink>
    <UiLoadError
      v-if="error && !recipes"
      @retry="refresh()"
    />
    <UiNavList
      v-else-if="items.length"
      label="Mes recettes"
      :items="items"
    />
    <UiCard v-else-if="recipes">
      <UiEmptyState
        :icon="CookingPot"
        title="Aucune recette"
        description="Compose un plat à partir de ses ingrédients pesés&nbsp;: il se saisira ensuite en portions."
      />
    </UiCard>
  </div>
</template>
