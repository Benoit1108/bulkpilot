<script setup lang="ts">
import RecipeComposition from '~/features/nutrition/components/RecipeComposition.vue'
import RecipeForm from '~/features/nutrition/components/RecipeForm.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import type { Recipe } from '~/features/nutrition/nutrition'
import { asApiError } from '~/services/api/client'

definePageMeta({ title: 'Recette' })

const route = useRoute()
const id = computed(() => String(route.params.id))
const api = useApi()
const { data: recipe, error, refresh } = useAsyncData(() => NUTRITION_KEYS.recipe(id.value), () => api.get('/api/recipes/{id}', { path: { id: id.value } }))
const gone = computed(() => error.value !== null && error.value !== undefined && asApiError(error.value).kind === 'not_found')
const notice = ref<string | null>(null)
const removal = useSubmission()

function onSaved(value: Recipe): void {
  recipe.value = value
  notice.value = 'Recette enregistrée. Les repas déjà saisis gardent leurs valeurs.'
  clearNuxtData(NUTRITION_KEYS.recipes)
}

async function destroy(): Promise<void> {
  if (await removal.run(() => api.delete('/api/recipes/{id}', { path: { id: id.value } }))) {
    clearNuxtData(key => key === NUTRITION_KEYS.recipes || key.startsWith('nutrition-recipe-'))
    await navigateTo('/nutrition/recettes')
  }
}
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/nutrition/recettes"
      label="Mes recettes"
    />
    <UiAlert
      v-if="gone"
      tone="error"
    >
      Cette recette n’existe plus.
    </UiAlert>
    <UiLoadError
      v-else-if="error && !recipe"
      @retry="refresh()"
    />
    <template v-else-if="recipe">
      <UiCard :title="recipe.name">
        <RecipeComposition :recipe="recipe" />
      </UiCard>
      <UiCard title="Modifier">
        <RecipeForm
          :key="recipe.id"
          :recipe="recipe"
          @saved="onSaved"
        />
      </UiCard>
      <UiAlert
        v-if="removal.message.value"
        tone="error"
      >
        {{ removal.message.value }}
      </UiAlert>
      <UiConfirmButton
        label="Supprimer la recette"
        :loading="removal.pending.value"
        @confirm="destroy"
      />
    </template>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
</template>
