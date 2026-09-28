<script setup lang="ts">
import { safeRedirect } from '~/features/identity/access'
import CustomFoodForm from '~/features/nutrition/components/CustomFoodForm.vue'
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'

definePageMeta({ title: 'Nouvel aliment' })

// Created from the journal's food search: back there, the new food is then in the search.
const route = useRoute()
const back = computed(() => (route.query.retour ? safeRedirect(route.query.retour) : '/nutrition/aliments'))

async function onSaved(): Promise<void> {
  clearNuxtData(NUTRITION_KEYS.customFoods)
  await navigateTo(back.value)
}
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      :to="back"
      :label="route.query.retour ? 'Retour' : 'Mes aliments'"
    />
    <UiCard>
      <CustomFoodForm @saved="onSaved" />
    </UiCard>
  </div>
</template>
