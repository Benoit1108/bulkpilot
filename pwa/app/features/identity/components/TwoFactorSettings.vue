<script setup lang="ts">
import type { Account } from '~/features/identity/account'
import PasswordConfirmForm from './PasswordConfirmForm.vue'
import RecoveryCodesPanel from './RecoveryCodesPanel.vue'
import TotpEnrollment from './TotpEnrollment.vue'

const props = defineProps<{ account: Account }>()

const api = useApi()
const { load } = useAccount()
const { pending, message, run, clear } = useSubmission()

type Step = 'idle' | 'confirm-enable' | 'scan' | 'codes' | 'confirm-regenerate' | 'confirm-disable'
const step = ref<Step>('idle')
const setup = ref<{ secret: string, otpauthUri: string } | null>(null)
const recoveryCodes = ref<string[]>([])

function go(next: Step): void {
  clear()
  step.value = next
}

function reset(): void {
  setup.value = null
  recoveryCodes.value = []
  go('idle')
}

async function start(password: string): Promise<void> {
  if (await run(async () => {
    setup.value = await api.post('/api/me/two-factor/setup', { password })
  })) go('scan')
}

async function confirm(code: string): Promise<void> {
  if (await run(async () => {
    recoveryCodes.value = (await api.post('/api/me/two-factor/confirm', { code })).recoveryCodes
  })) {
    go('codes')
    await load()
  }
}

async function regenerate(password: string): Promise<void> {
  if (await run(async () => {
    recoveryCodes.value = (await api.post('/api/me/two-factor/recovery-codes', { password })).recoveryCodes
  })) go('codes')
}

async function disable(password: string): Promise<void> {
  if (await run(() => api.post('/api/me/two-factor/disable', { password }))) {
    reset()
    await load()
  }
}
</script>

<template>
  <div class="two-factor">
    <RecoveryCodesPanel
      v-if="step === 'codes'"
      :codes="recoveryCodes"
      @done="reset"
    />

    <template v-else-if="props.account.twoFactorEnabled">
      <p class="two-factor__status two-factor__status--on">
        Active&nbsp;: un code de ton application est demandé à chaque connexion.
      </p>
      <PasswordConfirmForm
        v-if="step === 'confirm-regenerate' || step === 'confirm-disable'"
        :submit-label="step === 'confirm-disable' ? 'Désactiver' : 'Générer de nouveaux codes'"
        :pending="pending"
        :error="message ?? undefined"
        @submit="step === 'confirm-disable' ? disable($event) : regenerate($event)"
        @cancel="reset"
      />
      <div
        v-else
        class="form__actions"
      >
        <UiButton
          variant="secondary"
          @click="go('confirm-regenerate')"
        >
          Nouveaux codes de secours
        </UiButton>
        <UiButton
          variant="ghost"
          @click="go('confirm-disable')"
        >
          Désactiver
        </UiButton>
      </div>
    </template>

    <template v-else>
      <p class="two-factor__status">
        Protège ton compte avec un code généré par une application (Google Authenticator, 1Password, Aegis…).
      </p>
      <p
        v-if="!props.account.emailVerified"
        class="two-factor__hint"
      >
        Confirme d’abord ton adresse email pour pouvoir l’activer.
        <NuxtLink to="/reglages/compte#email">Renvoyer le lien</NuxtLink>
      </p>
      <p
        v-else-if="!props.account.hasPassword"
        class="two-factor__hint"
      >
        Définis d’abord un mot de passe&nbsp;: il est demandé pour activer ou désactiver la double authentification.
        <NuxtLink to="/mot-de-passe-oublie">Définir un mot de passe</NuxtLink>
      </p>
      <UiButton
        v-else-if="step === 'idle'"
        variant="secondary"
        @click="go('confirm-enable')"
      >
        Activer
      </UiButton>

      <PasswordConfirmForm
        v-if="step === 'confirm-enable'"
        submit-label="Continuer"
        :pending="pending"
        :error="message ?? undefined"
        @submit="start"
        @cancel="reset"
      />
      <TotpEnrollment
        v-if="step === 'scan' && setup"
        :secret="setup.secret"
        :otpauth-uri="setup.otpauthUri"
        :pending="pending"
        :error="message ?? undefined"
        @submit="confirm"
        @cancel="reset"
      />
    </template>
  </div>
</template>

<style scoped>
.two-factor {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.two-factor__status--on::before {
  content: '●';
  margin-right: var(--space-2);
  color: var(--color-success);
}

.two-factor__hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.two-factor__hint a {
  color: var(--color-accent-text);
  font-weight: 600;
}
</style>
