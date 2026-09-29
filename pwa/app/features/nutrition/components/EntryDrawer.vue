<script setup lang="ts">
import type { EntryChange } from '~/composables/useNutritionDay'
import QuantityForm from '~/features/nutrition/components/QuantityForm.vue'
import { toSlot, type Entry, type MealSlot } from '~/features/nutrition/nutrition'

const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{
  entry: Entry
  /** The day the entry is in (the journal's, not the URL's: they can differ while loading). */
  day: string
  correct: (entryId: string, change: EntryChange) => Promise<void>
  remove: (entryId: string) => Promise<void>
}>()
const emit = defineEmits<{ done: [message: string, slot: MealSlot] }>()

const { pending, message, fields, run, clear } = useSubmission()
const removal = useSubmission()

watch(open, (value) => {
  if (!value) {
    clear()
    removal.clear()
  }
})

async function save(grams: number, slot: MealSlot): Promise<void> {
  if (await run(() => props.correct(props.entry.id, { day: props.day, slot, grams }))) {
    open.value = false
    emit('done', `${props.entry.name} modifié.`, slot)
  }
}

async function destroy(): Promise<void> {
  if (await removal.run(() => props.remove(props.entry.id))) {
    open.value = false
    emit('done', `${props.entry.name} retiré ${toSlot(props.entry.slot)}.`, props.entry.slot)
  }
}
</script>

<template>
  <UiDrawer
    v-model:open="open"
    title="Modifier l’entrée"
  >
    <QuantityForm
      :name="entry.name"
      :per100g="entry.per100g"
      :initial-grams="entry.grams"
      :initial-slot="entry.slot"
      submit-label="Enregistrer"
      :pending="pending"
      :error="message ?? removal.message.value"
      :fields="fields"
      @submit="save"
    >
      <div class="mt-4 flex justify-end">
        <UiConfirmButton
          label="Supprimer l’entrée"
          variant="ghost"
          :loading="removal.pending.value"
          @confirm="destroy"
        />
      </div>
    </QuantityForm>
  </UiDrawer>
</template>
