<script setup lang="ts">
import { Apple } from 'lucide-vue-next'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { formatKcal } from '~/features/nutrition/format'

definePageMeta({ title: 'Mes aliments' })

const api = useApi()
const { data: foods, error, refresh } = useAsyncData(NUTRITION_KEYS.customFoods, () => api.get('/api/foods/custom'))
const items = computed(() => (foods.value ?? []).map(food => ({
  to: `/nutrition/aliments/${food.id}`,
  title: food.name,
  summary: [food.brand, `${formatKcal(food.per100g.energyKcal)} / 100\u00A0g`].filter(Boolean).join(' · '),
})))
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/nutrition"
      label="Nutrition"
    />
    <UiLinkButton
      to="/nutrition/aliments/nouveau"
    >
      Nouvel aliment
    </UiLinkButton>
    <UiLoadError
      v-if="error && !foods"
      @retry="refresh()"
    />
    <UiNavList
      v-else-if="items.length"
      label="Mes aliments"
      :items="items"
    />
    <UiCard v-else-if="foods">
      <UiEmptyState
        :icon="Apple"
        title="Aucun aliment personnel"
        description="Crée un aliment à partir de son étiquette quand il manque dans la base CIQUAL."
      />
    </UiCard>
  </div>
</template>
