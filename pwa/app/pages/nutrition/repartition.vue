<script setup lang="ts">
import MealSplitForm from '~/features/nutrition/components/MealSplitForm.vue'
import { isJournalDerived, NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import type { MealSplit } from '~/features/nutrition/nutrition'

definePageMeta({ title: 'Répartition des repas' })

const api = useApi()
const { data: split, error, refresh } = useAsyncData(NUTRITION_KEYS.mealSplit, () => api.get('/api/nutrition/meal-split'))
const notice = ref<string | null>(null)

function onSaved(value: MealSplit): void {
  split.value = value
  notice.value = 'Répartition enregistrée.'
  clearNuxtData(isJournalDerived)
}
</script>

<template>
  <UiPage>
    <ShellBackLink
      to="/nutrition"
      label="Nutrition"
    />
    <UiLoadError
      v-if="error && !split"
      @retry="refresh()"
    />
    <UiCard v-else-if="split">
      <MealSplitForm
        :split="split"
        @saved="onSaved"
      />
    </UiCard>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </UiPage>
</template>
