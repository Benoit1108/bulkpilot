<script setup lang="ts">
import { PASSWORD_HINT } from '~/features/identity/rules'

definePageMeta({ title: 'Nouveau mot de passe', layout: 'auth', public: true })

const route = useRoute()
const router = useRouter()
const api = useApi()
const { account, forget } = useAccount()
const { pending, error, message, fields, run } = useSubmission()
const newPassword = ref('')
const done = ref(false)

// Read once, then removed from the address bar and history.
const token = typeof route.query.token === 'string' ? route.query.token : ''
onMounted(() => {
  if (route.query.token) void router.replace({ query: {} })
})
const deadLink = computed(() => token === '' || error.value?.code === 'identity.invalid_token')

async function submit(): Promise<void> {
  done.value = await run(() => api.post('/api/auth/password-reset', { token, newPassword: newPassword.value }))
  // Every session was revoked, including this one.
  if (done.value && account.value) forget()
}
</script>

<template>
  <div class="form">
    <UiAlert
      v-if="done"
      tone="success"
    >
      Ton mot de passe a été changé. Par sécurité, tous tes appareils ont été déconnectés.
    </UiAlert>
    <template v-else-if="deadLink">
      <UiAlert tone="error">
        Ce lien est invalide, expiré ou a déjà été utilisé.
      </UiAlert>
      <p class="form__links">
        <NuxtLink to="/mot-de-passe-oublie">Demander un nouveau lien</NuxtLink>
      </p>
    </template>
    <form
      v-else
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
        v-model="newPassword"
        label="Nouveau mot de passe"
        type="password"
        autocomplete="new-password"
        :hint="PASSWORD_HINT"
        required
        :error="fields.newPassword"
      />
      <UiButton
        type="submit"
        :loading="pending"
      >
        Enregistrer
      </UiButton>
    </form>
    <p class="form__links">
      <NuxtLink to="/connexion">Aller à la connexion</NuxtLink>
    </p>
  </div>
</template>
