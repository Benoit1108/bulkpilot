<script setup lang="ts">
import type { Account } from '~/features/identity/account'

withDefaults(defineProps<{ account: Account, compact?: boolean }>(), { compact: false })

const api = useApi()
const { pending, message, run } = useSubmission()
const sent = ref(false)

async function resend(): Promise<void> {
  sent.value = await run(() => api.post('/api/me/email-verification'))
}
</script>

<template>
  <div class="flex flex-col items-start gap-3">
    <p
      v-if="!compact"
      class="flex flex-wrap items-center gap-2"
    >
      <span class="font-semibold break-all">{{ account.email }}</span>
      <span
        class="rounded-full px-2 py-0.5 text-xs font-bold"
        :class="account.emailVerified ? 'bg-success/18' : 'bg-danger/18'"
      >{{ account.emailVerified ? 'Confirmée' : 'À confirmer' }}</span>
    </p>
    <template v-if="!account.emailVerified">
      <p class="text-sm text-muted">
        {{ compact
          ? 'Confirme ton adresse email grâce au lien reçu à l’inscription.'
          : 'Confirme ton adresse pour pouvoir récupérer ton compte en cas d’oubli du mot de passe.' }}
      </p>
      <p
        v-if="sent"
        role="status"
        class="text-sm text-muted"
      >
        Un nouveau lien vient d’être envoyé à {{ account.email }}.
      </p>
      <UiAlert
        v-if="message"
        tone="error"
      >
        {{ message }}
      </UiAlert>
      <UiButton
        v-if="!sent"
        variant="secondary"
        :loading="pending"
        @click="resend"
      >
        Renvoyer le lien
      </UiButton>
    </template>
  </div>
</template>
