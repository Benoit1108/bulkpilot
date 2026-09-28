<script setup lang="ts">
import { Star } from 'lucide-vue-next'
import { formatGrams, formatKcal } from '~/features/nutrition/format'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import type { FavoriteMeal } from '~/features/nutrition/nutrition'

definePageMeta({ title: 'Repas favoris' })

const api = useApi()
const { data: favorites, error, refresh } = useAsyncData(NUTRITION_KEYS.favoriteMeals, () => api.get('/api/nutrition/favorite-meals'))
const { pending, message, run } = useSubmission()
const notice = ref<string | null>(null)

async function remove(meal: FavoriteMeal): Promise<void> {
  if (await run(() => api.delete('/api/nutrition/favorite-meals/{id}', { path: { id: meal.id } }))) {
    notice.value = `« ${meal.name} » supprimé des repas favoris.`
    await refresh()
  }
}
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/nutrition"
      label="Nutrition"
    />
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiLoadError
      v-if="error && !favorites"
      @retry="refresh()"
    />
    <UiCard v-else-if="favorites && !favorites.length">
      <UiEmptyState
        :icon="Star"
        title="Aucun repas favori"
        description="Depuis le journal, enregistre un repas que tu prends souvent («&nbsp;Favori&nbsp;» sous le repas)."
      />
    </UiCard>
    <UiCard
      v-for="meal in favorites ?? []"
      :key="meal.id"
      :title="meal.name"
      as="article"
    >
      <template #actions>
        <UiConfirmButton
          variant="ghost"
          label="Supprimer"
          :aria-describedby="`favorite-${meal.id}`"
          :loading="pending"
          @confirm="remove(meal)"
        />
      </template>
      <ul class="items">
        <li
          v-for="(item, index) in meal.items"
          :key="index"
        >
          {{ item.name }} · {{ formatGrams(item.grams) }}
        </li>
      </ul>
      <p
        :id="`favorite-${meal.id}`"
        class="visually-hidden"
      >
        {{ meal.name }}
      </p>
      <p class="total">
        {{ formatKcal(meal.totals.energyKcal) }}
      </p>
    </UiCard>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
</template>

<style scoped>
.items {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  color: var(--color-text-muted);
}

.total {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}
</style>
