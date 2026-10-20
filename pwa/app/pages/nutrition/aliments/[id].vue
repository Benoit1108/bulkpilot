<script setup lang="ts">
import CustomFoodForm from '~/features/nutrition/components/CustomFoodForm.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { asApiError } from '~/services/api/client'

definePageMeta({ title: 'Modifier l’aliment' })

const route = useRoute()
const id = computed(() => String(route.params.id))
const api = useApi()
const { data: food, error, refresh } = useAsyncData(() => NUTRITION_KEYS.food(id.value), () => api.get('/api/foods/{id}', { path: { id: id.value } }))
const gone = computed(() => error.value !== null && error.value !== undefined && asApiError(error.value).kind === 'not_found')
const removal = useSubmission()

async function leave(): Promise<void> {
  clearNuxtData(key => key === NUTRITION_KEYS.customFoods || key === NUTRITION_KEYS.recentFoods || key.startsWith('nutrition-food-'))
  await navigateTo('/nutrition/aliments')
}

async function destroy(): Promise<void> {
  if (await removal.run(() => api.delete('/api/foods/{id}', { path: { id: id.value } }))) await leave()
}
</script>

<template>
  <UiPage>
    <ShellBackLink
      to="/nutrition/aliments"
      label="Mes aliments"
    />
    <UiAlert
      v-if="gone"
      tone="error"
    >
      Cet aliment n’existe plus.
    </UiAlert>
    <UiLoadError
      v-else-if="error && !food"
      @retry="refresh()"
    />
    <template v-else-if="food">
      <UiCard v-if="food.editable">
        <CustomFoodForm
          :food="food"
          @saved="leave"
        />
      </UiCard>
      <UiAlert
        v-else
        tone="info"
      >
        Les aliments de la base CIQUAL ne se modifient pas.
      </UiAlert>
      <template v-if="food.editable">
        <UiAlert
          v-if="removal.message.value"
          tone="error"
        >
          {{ removal.message.value }}
        </UiAlert>
        <UiConfirmButton
          label="Supprimer cet aliment"
          :loading="removal.pending.value"
          @confirm="destroy"
        />
        <p class="text-sm text-muted">
          Les repas déjà saisis gardent leurs valeurs.
        </p>
      </template>
    </template>
  </UiPage>
</template>
