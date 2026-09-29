<script setup lang="ts">
definePageMeta({ title: 'Mot de passe oublié', layout: 'auth', public: true })

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const email = ref('')
const sent = ref(false)

async function submit(): Promise<void> {
  sent.value = await run(() => api.post('/api/auth/password-reset-request', { email: email.value }))
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <UiAlert
      v-if="sent"
      tone="success"
    >
      Si un compte avec une adresse confirmée existe pour {{ email }}, un lien de réinitialisation vient d’y être envoyé. Il est valable 1 heure.
    </UiAlert>
    <form
      v-else
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
      <p class="text-muted">
        Indique l’adresse de ton compte&nbsp;: tu recevras un lien pour choisir un nouveau mot de passe.
      </p>
      <UiInput
        v-model="email"
        label="Email"
        type="email"
        autocomplete="email"
        inputmode="email"
        required
        :error="fields.email"
      />
      <UiButton
        type="submit"
        :loading="pending"
      >
        Envoyer le lien
      </UiButton>
    </form>
    <UiLinkRow>
      <NuxtLink to="/connexion">Retour à la connexion</NuxtLink>
    </UiLinkRow>
  </div>
</template>
