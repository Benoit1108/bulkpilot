<script setup lang="ts">
import ExternalSignIn from '~/features/identity/components/ExternalSignIn.vue'
import { PASSWORD_HINT } from '~/features/identity/rules'

definePageMeta({ title: 'Créer un compte', layout: 'auth', public: true, guestOnly: true })

const { register } = useAccount()
const { pending, message, fields, run } = useSubmission()
const firstName = ref('')
const email = ref('')
const password = ref('')

async function submit(): Promise<void> {
  if (await run(() => register(firstName.value, email.value, password.value))) {
    await navigateTo('/')
  }
}
</script>

<template>
  <form
    class="form"
    novalidate
    @submit.prevent="submit"
  >
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiInput
      v-model="firstName"
      label="Prénom"
      autocomplete="given-name"
      required
      :error="fields.firstName"
    />
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
      autocomplete="new-password"
      :hint="PASSWORD_HINT"
      required
      :error="fields.password"
    />
    <UiButton
      type="submit"
      :loading="pending"
    >
      Créer mon compte
    </UiButton>
    <p class="form__links">
      <NuxtLink to="/connexion">J’ai déjà un compte</NuxtLink>
    </p>
    <ExternalSignIn />
  </form>
</template>
