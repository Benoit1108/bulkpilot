<script setup lang="ts">
import { NUTRITION_KEYS } from '~/features/nutrition/cacheKeys'
import { slotLabel, type MealSlot } from '~/features/nutrition/nutrition'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{ day: string, mealSlot: MealSlot }>()
const emit = defineEmits<{ saved: [name: string] }>()

const api = useApi()
const name = ref('')
const { pending, message, fields, run, clear } = useSubmission()

watch(open, (value) => {
  if (value) name.value = slotLabel(props.mealSlot)
  else clear()
})

async function save(): Promise<void> {
  const saved = await run(async () => {
    await api.post('/api/nutrition/favorite-meals', { name: name.value, day: props.day, slot: props.mealSlot })
  })
  if (saved) {
    clearNuxtData(NUTRITION_KEYS.favoriteMeals)
    emit('saved', name.value)
    open.value = false
  }
}
</script>

<template>
  <UiDrawer
    v-model:open="open"
    title="Enregistrer comme repas favori"
  >
    <form
      class="flex flex-col gap-3"
      novalidate
      @submit.prevent="save"
    >
      <p class="text-muted">
        Tu pourras le rajouter en un geste, dans n’importe quel repas. Il suit les valeurs actuelles de ses aliments et recettes.
      </p>
      <UiAlert
        v-if="message"
        tone="error"
      >
        {{ message }}
      </UiAlert>
      <UiInput
        v-model="name"
        label="Nom du repas"
        :maxlength="100"
        required
        autofocus
        :error="fields.name"
      />
      <UiButton
        type="submit"
        :loading="pending"
      >
        Enregistrer
      </UiButton>
    </form>
  </UiDrawer>
</template>
