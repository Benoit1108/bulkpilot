<script setup lang="ts">
import { safeRedirect } from '~/features/identity/access'
import ExternalSignIn from '~/features/identity/components/ExternalSignIn.vue'
import { messageForCode } from '~/services/api/messages'

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
    class="form"
    novalidate
    @submit.prevent="submitPassword"
  >
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
    <p class="form__links">
      <NuxtLink to="/mot-de-passe-oublie">Mot de passe oublié&#x202F;?</NuxtLink>
      <NuxtLink to="/inscription">Créer un compte</NuxtLink>
    </p>
    <ExternalSignIn :redirect="redirectTarget" />
  </form>

  <form
    v-else
    class="form"
    novalidate
    @submit.prevent="submitCode"
  >
    <p class="form__intro">
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
    <p class="form__links">
      <button
        type="button"
        class="link-button"
        @click="toggleRecoveryCode"
      >
        {{ useRecoveryCode ? 'Utiliser l’application' : 'Utiliser un code de secours' }}
      </button>
      <button
        type="button"
        class="link-button"
        @click="restart"
      >
        Retour
      </button>
    </p>
  </form>
</template>
