<script setup lang="ts">
import { safeRedirect } from '~/features/identity/access'
import ExternalSignIn from '~/features/identity/components/ExternalSignIn.vue'
import { messageForCode } from '~/services/api/messages'
import { formatFullDay, isDay } from '~/services/calendar/days'

definePageMeta({ title: 'Connexion', layout: 'auth', public: true, guestOnly: true })

const route = useRoute()
const { signIn, completeSignIn } = useAccount()
const { pending, message, fields, run, clear } = useSubmission()
const email = ref('')
const password = ref('')
const code = ref('')
// Back from an external provider: either the second factor is expected, or something failed.
const step = ref<'password' | 'second-factor'>(route.query.second_factor === '1' ? 'second-factor' : 'password')
const useRecoveryCode = ref(false)
const redirectTarget = computed(() => safeRedirect(route.query.redirect))
const externalError = computed(() => (typeof route.query.oauth_error === 'string' ? messageForCode(route.query.oauth_error) : null))
// Arriving from « Supprimer mon compte »: when it happens, and how to undo it.
const deletionNotice = computed(() => {
  const due = route.query.suppression
  if (typeof due !== 'string') return null
  const when = isDay(due) ? ` le ${formatFullDay(due)}` : ''
  return `Ton compte sera supprimé${when} et tous tes appareils sont déconnectés. Te reconnecter d’ici là annule la suppression.`
})
// Said first: the focus goes to it (a region inserted with its text is not always read).
const deletionBanner = ref<HTMLElement | null>(null)
onMounted(() => deletionBanner.value?.focus())

async function done(): Promise<void> {
  await navigateTo(redirectTarget.value)
}

async function submitPassword(): Promise<void> {
  let twoFactorRequired = false
  const ok = await run(async () => {
    twoFactorRequired = (await signIn(email.value, password.value)).twoFactorRequired
  })
  if (!ok) {
    password.value = ''
    return
  }
  if (twoFactorRequired) {
    clear()
    step.value = 'second-factor'
  }
  else await done()
}

async function submitCode(): Promise<void> {
  if (await run(() => completeSignIn(code.value))) await done()
}

function restart(): void {
  clear()
  step.value = 'password'
  code.value = ''
  password.value = ''
}

function toggleRecoveryCode(): void {
  clear()
  useRecoveryCode.value = !useRecoveryCode.value
  code.value = ''
}
</script>

<template>
  <form
    v-if="step === 'password'"
    class="flex flex-col gap-4"
    novalidate
    @submit.prevent="submitPassword"
  >
    <div
      v-if="deletionNotice"
      ref="deletionBanner"
      tabindex="-1"
      class="focus:outline-none focus-visible:outline-2 focus-visible:outline-accent"
    >
      <UiAlert tone="info">
        {{ deletionNotice }}
      </UiAlert>
    </div>
    <UiAlert
      v-if="message || externalError"
      tone="error"
    >
      {{ message ?? externalError }}
    </UiAlert>
    <UiInput
      v-model="email"
      label="Email"
      type="email"
      autocomplete="email"
      inputmode="email"
      required
      :error="fields.email"
    />
    <UiInput
      v-model="password"
      label="Mot de passe"
      type="password"
      autocomplete="current-password"
      required
      :error="fields.password"
    />
    <UiButton
      type="submit"
      :loading="pending"
    >
      Se connecter
    </UiButton>
    <UiLinkRow>
      <NuxtLink to="/mot-de-passe-oublie">Mot de passe oublié&#x202F;?</NuxtLink>
      <NuxtLink to="/inscription">Créer un compte</NuxtLink>
    </UiLinkRow>
    <ExternalSignIn :redirect="redirectTarget" />
  </form>

  <form
    v-else
    class="flex flex-col gap-4"
    novalidate
    @submit.prevent="submitCode"
  >
    <p class="text-muted">
      {{ useRecoveryCode
        ? 'Saisis l’un de tes codes de secours. Chaque code ne fonctionne qu’une fois.'
        : 'Saisis le code à 6 chiffres affiché par ton application d’authentification.' }}
    </p>
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiInput
      v-if="useRecoveryCode"
      v-model="code"
      label="Code de secours"
      autocomplete="off"
      literal
      autofocus
      required
    />
    <UiInput
      v-else
      v-model="code"
      label="Code de vérification"
      inputmode="numeric"
      autocomplete="one-time-code"
      :maxlength="7"
      pattern="[0-9 ]*"
      autofocus
      required
    />
    <UiButton
      type="submit"
      :loading="pending"
    >
      Valider
    </UiButton>
    <div class="flex flex-wrap justify-between gap-3">
      <UiButton
        variant="ghost"
        @click="toggleRecoveryCode"
      >
        {{ useRecoveryCode ? 'Utiliser l’application' : 'Utiliser un code de secours' }}
      </UiButton>
      <UiButton
        variant="ghost"
        @click="restart"
      >
        Retour
      </UiButton>
    </div>
  </form>
</template>
