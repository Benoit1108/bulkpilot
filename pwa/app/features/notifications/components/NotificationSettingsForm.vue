<script setup lang="ts">
import { fieldClass } from '~/components/ui/classes'
import { CATEGORIES, DEFAULT_QUIET_HOURS, hasQuietHours, type NotificationSettings } from '../notifications'

/**
 * Which notifications arrive (spec §75) and when none do (spec §76). Only the important ones
 * exist, each can be turned off.
 */
const props = defineProps<{ settings: NotificationSettings }>()
const emit = defineEmits<{ saved: [settings: NotificationSettings] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const categories = reactive({ ...props.settings.categories })
const quiet = ref(hasQuietHours(props.settings))
const quietFrom = ref(props.settings.quietFrom ?? DEFAULT_QUIET_HOURS.from)
const quietTo = ref(props.settings.quietTo ?? DEFAULT_QUIET_HOURS.to)
const saved = ref<string | null>(null)
const id = useId()
const timeError = computed(() => fields.value.quietFrom ?? fields.value.quietTo)

/** No quiet hours: neither end. */
function body() {
  return {
    categories: { ...categories },
    quietFrom: quiet.value ? quietFrom.value : null,
    quietTo: quiet.value ? quietTo.value : null,
  }
}
const { markSaved } = useUnsavedChanges(body)

async function submit(): Promise<void> {
  saved.value = null
  const sent = body()
  await run(async () => {
    const settings = await api.put('/api/notifications/settings', sent)
    markSaved(sent)
    saved.value = 'Réglages des notifications enregistrés.'
    emit('saved', settings)
  })
}
</script>

<template>
  <form
    class="flex flex-col gap-4"
    novalidate
    @submit.prevent="submit"
  >
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <fieldset
      class="m-0 flex min-w-0 flex-col border-0 p-0"
      :aria-describedby="`${id}-categories`"
    >
      <legend class="mb-1 font-semibold">
        Ce qui t’est signalé
      </legend>
      <p
        :id="`${id}-categories`"
        class="text-sm text-muted"
      >
        Seulement l’important, dans l’application. Décoche ce que tu ne veux pas recevoir.
      </p>
      <UiCheckbox
        v-for="category in CATEGORIES"
        :key="category.value"
        v-model="categories[category.value]"
        :label="category.label"
        :hint="category.description"
      />
    </fieldset>
    <fieldset class="m-0 flex min-w-0 flex-col gap-3 border-0 p-0">
      <legend class="mb-1 font-semibold">
        Horaires silencieux
      </legend>
      <UiCheckbox
        v-model="quiet"
        label="Activer les horaires silencieux"
        hint="Aucune notification n’est créée entre ces deux heures ; elles ne sont pas rattrapées ensuite."
      />
      <div
        v-if="quiet"
        class="grid grid-cols-2 gap-3"
      >
        <div :class="fieldClass.root">
          <label
            :for="`${id}-from`"
            :class="fieldClass.label"
          >À partir de</label>
          <input
            :id="`${id}-from`"
            v-model="quietFrom"
            type="time"
            required
            class="h-tap px-3 text-base"
            :class="fieldClass.box"
            :aria-invalid="timeError ? 'true' : undefined"
            :aria-describedby="timeError ? `${id}-time-error` : undefined"
          >
        </div>
        <div :class="fieldClass.root">
          <label
            :for="`${id}-to`"
            :class="fieldClass.label"
          >Jusqu’à</label>
          <input
            :id="`${id}-to`"
            v-model="quietTo"
            type="time"
            required
            class="h-tap px-3 text-base"
            :class="fieldClass.box"
            :aria-invalid="timeError ? 'true' : undefined"
            :aria-describedby="timeError ? `${id}-time-error` : undefined"
          >
        </div>
        <p
          v-if="timeError"
          :id="`${id}-time-error`"
          class="col-span-2"
          :class="fieldClass.error"
        >
          {{ timeError }}
        </p>
      </div>
    </fieldset>
    <div class="flex flex-wrap items-center gap-3">
      <UiButton
        type="submit"
        :loading="pending"
      >
        Enregistrer
      </UiButton>
      <UiStatus
        :message="saved"
        @dismiss="saved = null"
      />
    </div>
  </form>
</template>
