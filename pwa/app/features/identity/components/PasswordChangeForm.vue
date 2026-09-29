<script setup lang="ts">
import { PASSWORD_HINT } from '~/features/identity/rules'

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const currentPassword = ref('')
const newPassword = ref('')
const changed = ref(false)

async function submit(): Promise<void> {
  changed.value = false
  changed.value = await run(() => api.post('/api/me/password', { currentPassword: currentPassword.value, newPassword: newPassword.value }))
  if (changed.value) {
    currentPassword.value = ''
    newPassword.value = ''
  }
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
      v-if="changed"
      tone="success"
    >
      Mot de passe changé. Tes autres appareils ont été déconnectés.
    </UiAlert>
    <UiInput
      v-model="currentPassword"
      label="Mot de passe actuel"
      type="password"
      autocomplete="current-password"
      required
      :error="fields.currentPassword"
    />
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
      variant="secondary"
      :loading="pending"
    >
      Changer le mot de passe
    </UiButton>
  </form>
</template>
