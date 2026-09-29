<script setup lang="ts">
import { parseAmount, signedAmountText } from '~/services/text/numbers'
import { PERSONALITIES, type CoachSettings } from '../coach'

/**
 * How the Coach speaks (spec §131) and the pace it judges the weight against. The tone changes
 * the wording only; the rules stay the same.
 */
const props = defineProps<{ settings: CoachSettings }>()
const emit = defineEmits<{ saved: [settings: CoachSettings] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const personality = ref(props.settings.personality)
const pace = ref<string | number | null>(props.settings.paceKgPerWeek === null ? '' : signedAmountText(props.settings.paceKgPerWeek))
const saved = ref<string | null>(null)
const unreadable = ref(false)
const groupHint = useId()
const hintId = (value: string) => `${groupHint}-${value}`

const blank = () => (typeof pace.value === 'string' ? pace.value.trim() === '' : pace.value === null)
/**
 * Signed kilograms a week, as typed (the server says when it is not to the hundredth); blank to
 * follow the nutrition target.
 */
function body() {
  return { personality: personality.value, paceKgPerWeek: blank() ? null : parseAmount(pace.value) }
}
const { markSaved } = useUnsavedChanges(body)
const paceError = computed(() => (unreadable.value ? 'Un nombre de kilos par semaine, par exemple +0,5 ou −0,3.' : fields.value.paceKgPerWeek))

async function submit(): Promise<void> {
  saved.value = null
  const sent = body()
  // Something typed that is no number must not clear the pace silently.
  unreadable.value = !blank() && sent.paceKgPerWeek === null
  if (unreadable.value) return
  await run(async () => {
    const settings = await api.put('/api/coach/settings', sent)
    markSaved(sent)
    saved.value = 'Réglages du Coach enregistrés.'
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
      class="m-0 flex min-w-0 flex-col gap-2 border-0 p-0"
      :aria-describedby="groupHint"
    >
      <legend class="mb-1 font-semibold">
        Ton du Coach
      </legend>
      <p
        :id="groupHint"
        class="text-sm text-muted"
      >
        Il change la façon de dire les choses, jamais les recommandations elles-mêmes.
      </p>
      <label
        v-for="option in PERSONALITIES"
        :key="option.value"
        class="flex min-h-tap cursor-pointer items-start gap-3 rounded-md border border-line bg-surface-raised p-3 has-checked:border-accent has-checked:bg-accent-soft has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-accent"
      >
        <input
          v-model="personality"
          type="radio"
          name="personality"
          :value="option.value"
          :aria-describedby="hintId(option.value)"
          class="mt-1 accent-accent-fill"
        >
        <span class="flex flex-col">
          <span class="font-semibold">{{ option.label }}</span>
          <span
            :id="hintId(option.value)"
            class="text-sm text-muted"
            aria-hidden="true"
          >{{ option.description }}</span>
        </span>
      </label>
    </fieldset>
    <UiInput
      v-model="pace"
      label="Rythme visé"
      suffix="kg par semaine"
      hint="Par exemple +0,5 pour prendre 0,5&nbsp;kg par semaine, −0,3 pour en perdre. Laisse vide pour suivre le rythme de ton objectif nutrition."
      :error="paceError"
    />
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
