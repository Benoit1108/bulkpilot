<script setup lang="ts">
import { buttonClass } from '~/components/ui/classes'
import { useWorkoutOutbox } from '~/features/training/outbox'
import { localDay } from '~/services/calendar/days'
import { CONFIRMATION_WORD } from '../deletion'

/**
 * Deleting the account (spec §103): a strong confirmation — the password (or, for an account
 * without one, a fresh sign-in), the second factor when it is on, and the word typed — then the
 * account is closed at once and erased later.
 */
const props = defineProps<{
  hasPassword: boolean
  twoFactorEnabled: boolean
  /** The id of the summary of what happens, read with the destructive button. */
  consequences?: string
  /** How recent a sign-in must be for an account without a password, in minutes. */
  reauthenticationMinutes?: number
}>()

const api = useApi()
const { closed } = useAccount()
const outbox = useWorkoutOutbox()
const password = ref('')
const code = ref('')
const word = ref('')
const { pending, message, fields, run, clear } = useSubmission()
const invalid = ref<Record<string, string>>({})

// Workout writes still waiting to reach the server would be lost with the device's data.
const unsent = computed(() => outbox.state.pending + outbox.state.failed.length)

// An account without a password confirms with a fresh sign-in, back here afterwards.
const { data: providers } = useAsyncData('identity-providers', () => api.get('/api/auth/providers'))
const signInAgain = (provider: string) => `/api/auth/oauth/${encodeURIComponent(provider)}/start?${new URLSearchParams({ redirect: '/reglages/suppression' })}`
const PROVIDER_NAMES: Record<string, string> = { google: 'Google', apple: 'Apple' }
const reauthenticationNote = computed(() => `Ton compte n’a pas de mot de passe\u00A0: pour confirmer, reconnecte-toi juste avant${props.reauthenticationMinutes ? ` (moins de ${props.reauthenticationMinutes}\u00A0minutes)` : ''}, puis reviens ici.`)

const passwordField = ref<{ focus: () => void } | null>(null)
const codeField = ref<{ focus: () => void } | null>(null)
const wordField = ref<{ focus: () => void } | null>(null)

async function submit(): Promise<void> {
  clear()
  invalid.value = {
    ...(props.hasPassword && !password.value && { password: 'Saisis ton mot de passe.' }),
    ...(props.twoFactorEnabled && !code.value.trim() && { code: 'Saisis le code de ton application ou un code de secours.' }),
    ...(word.value.trim().toUpperCase() !== CONFIRMATION_WORD && { word: `Écris ${CONFIRMATION_WORD} pour confirmer.` }),
  }
  if (Object.keys(invalid.value).length) {
    // The first field to fix gets the focus: its error is read at once.
    await nextTick()
    const first = invalid.value.password ? passwordField : invalid.value.code ? codeField : wordField
    first.value?.focus()
    return
  }
  // Filled by the request (a plain `let` would stay typed as its initial null).
  const scheduled: { dueAt: string | null } = { dueAt: null }
  const done = await run(async () => {
    scheduled.dueAt = (await api.post('/api/me/deletion', {
      password: password.value,
      ...(props.twoFactorEnabled && { code: code.value.trim() }),
    })).deletionDueAt
  })
  if (!done || scheduled.dueAt === null) {
    password.value = ''
    return
  }
  const due = localDay(new Date(scheduled.dueAt))
  // Every session is revoked, this one too: nothing of the account stays on the device. The
  // sign-in page says when it happens, even if the device's storage could not be emptied.
  await closed()
  await navigateTo({ path: '/connexion', query: { suppression: due } })
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
    <UiAlert
      v-if="unsent"
      tone="info"
    >
      Des séances enregistrées hors ligne n’ont pas encore été envoyées&nbsp;: elles seront perdues avec ton compte.
    </UiAlert>
    <div
      v-if="!hasPassword"
      class="flex flex-col gap-3"
    >
      <p class="text-sm">
        {{ reauthenticationNote }}
      </p>
      <a
        v-for="provider in providers?.providers ?? []"
        :key="provider"
        :class="buttonClass('secondary')"
        :href="signInAgain(provider)"
      >
        Me reconnecter avec {{ PROVIDER_NAMES[provider] ?? provider }}
      </a>
    </div>
    <UiInput
      v-if="hasPassword"
      ref="passwordField"
      v-model="password"
      label="Ton mot de passe"
      type="password"
      autocomplete="current-password"
      required
      :error="invalid.password ?? fields.password"
    />
    <UiInput
      v-if="twoFactorEnabled"
      ref="codeField"
      v-model="code"
      label="Code de double authentification"
      hint="Le code de ton application, ou un code de secours."
      autocomplete="one-time-code"
      literal
      required
      :error="invalid.code ?? fields.code"
    />
    <UiInput
      ref="wordField"
      v-model="word"
      :label="`Écris ${CONFIRMATION_WORD} pour confirmer`"
      autocomplete="off"
      literal
      required
      :error="invalid.word"
    />
    <UiButton
      type="submit"
      variant="danger"
      :loading="pending"
      :aria-describedby="consequences"
    >
      Supprimer mon compte
    </UiButton>
  </form>
</template>
