<script setup lang="ts">
import RecipeForm from '~/features/nutrition/components/RecipeForm.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import type { Recipe } from '~/features/nutrition/nutrition'

definePageMeta({ title: 'Nouvelle recette' })

async function onSaved(recipe: Recipe): Promise<void> {
  clearNuxtData(NUTRITION_KEYS.recipes)
  await navigateTo(`/nutrition/recettes/${recipe.id}`)
}
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/nutrition/recettes"
      label="Mes recettes"
    />
    <UiCard>
      <RecipeForm @saved="onSaved" />
    </UiCard>
  </div>
</template>
