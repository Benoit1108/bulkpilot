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
  <div class="email-status">
    <p v-if="!compact">
      <span class="email-status__address">{{ account.email }}</span>
      <span
        class="email-status__badge"
        :class="account.emailVerified ? 'email-status__badge--ok' : 'email-status__badge--todo'"
      >{{ account.emailVerified ? 'Confirmée' : 'À confirmer' }}</span>
    </p>
    <template v-if="!account.emailVerified">
      <p class="email-status__help">
        {{ compact
          ? 'Confirme ton adresse email grâce au lien reçu à l’inscription.'
          : 'Confirme ton adresse pour pouvoir récupérer ton compte en cas d’oubli du mot de passe.' }}
      </p>
      <p
        v-if="sent"
        role="status"
        class="email-status__help"
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

<style scoped>
.email-status {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-3);
}

.email-status p:first-child {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.email-status__address {
  font-weight: 600;
  word-break: break-all;
}

.email-status__badge {
  padding: 2px var(--space-2);
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: 700;
}

.email-status__badge--ok {
  background: color-mix(in srgb, var(--color-success) 18%, transparent);
}

.email-status__badge--todo {
  background: color-mix(in srgb, var(--color-danger) 18%, transparent);
}

.email-status__help {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
