<script setup lang="ts">
import type { Measure } from '../progression'

/**
 * The user's own measures (spec §54): added, renamed, archived (their values stay).
 */
const props = defineProps<{ measures: Measure[] }>()
const emit = defineEmits<{ changed: [] }>()

const MAX = 20
const api = useApi()
const adding = useSubmission()
const editing = useSubmission()
const name = ref('')
const names = reactive<Record<string, string>>({})
watch(() => props.measures, (measures) => {
  for (const measure of measures) names[measure.key] ??= measure.name ?? ''
}, { immediate: true })
const own = computed(() => props.measures.filter(measure => measure.custom))
const notice = ref<string | null>(null)

async function add(): Promise<void> {
  if (await adding.run(async () => {
    const measure = await api.post('/api/progression/measures', { name: name.value })
    notice.value = `Mesure «\u00A0${measure.name}\u00A0» ajoutée.`
  })) {
    name.value = ''
    emit('changed')
  }
}

async function rename(measure: Measure): Promise<void> {
  if (await editing.run(async () => {
    await api.put('/api/progression/measures/{key}', { name: names[measure.key] ?? '' }, { path: { key: measure.key } })
    notice.value = 'Mesure renommée.'
  })) emit('changed')
}

const root = ref<HTMLElement | null>(null)
const archived = ref<string | null>(null)
async function archive(measure: Measure): Promise<void> {
  if (await editing.run(async () => {
    await api.delete('/api/progression/measures/{key}', { path: { key: measure.key } })
    notice.value = `Mesure «\u00A0${measure.name}\u00A0» retirée du formulaire (ses valeurs sont gardées).`
  })) {
    archived.value = measure.key
    emit('changed')
  }
}
// Once the list no longer shows it, focus goes to the first measure left, else to the new one's field.
watch(own, async (measures) => {
  if (archived.value === null || measures.some(measure => measure.key === archived.value)) return
  archived.value = null
  await nextTick()
  root.value?.querySelector<HTMLElement>('li input, form input')?.focus()
})
</script>

<template>
  <div
    ref="root"
    class="flex flex-col gap-4"
  >
    <UiAlert
      v-if="editing.message.value"
      tone="error"
    >
      {{ editing.message.value }}
    </UiAlert>
    <ul
      v-if="own.length"
      class="m-0 flex list-none flex-col gap-3 p-0"
    >
      <li
        v-for="measure in own"
        :key="measure.key"
        class="flex flex-wrap items-end gap-2 border-t border-line pt-3"
      >
        <UiInput
          v-model="names[measure.key]"
          class="min-w-48 flex-1"
          :label="`Nom de la mesure ${measure.name}`"
          :maxlength="40"
        />
        <UiButton
          variant="secondary"
          :aria-label="`Renommer ${measure.name}`"
          @click="rename(measure)"
        >
          Renommer
        </UiButton>
        <UiConfirmButton
          label="Retirer"
          :accessible-label="`Retirer ${measure.name}`"
          confirm-label="Confirmer"
          variant="ghost"
          @confirm="archive(measure)"
        />
      </li>
    </ul>
    <p
      v-else
      class="text-muted"
    >
      Aucune mesure perso pour l’instant.
    </p>
    <form
      class="flex flex-col gap-3"
      novalidate
      @submit.prevent="add"
    >
      <UiAlert
        v-if="adding.message.value"
        tone="error"
      >
        {{ adding.message.value }}
      </UiAlert>
      <UiInput
        v-model="name"
        label="Nouvelle mesure"
        :maxlength="40"
        hint="Par exemple «&nbsp;Hanches&nbsp;» ou «&nbsp;Avant-bras gauche&nbsp;», en centimètres."
        :error="adding.fields.value.name"
      />
      <UiButton
        type="submit"
        class="self-start"
        :loading="adding.pending.value"
        :disabled="own.length >= MAX"
      >
        Ajouter la mesure
      </UiButton>
    </form>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
</template>
