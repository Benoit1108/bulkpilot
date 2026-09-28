<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'
import type { ThemePreference } from '~/features/shell/theme'
import { useServiceHealth } from '~/features/system/health'

definePageMeta({ title: 'Réglages' })

const { account, signOut } = useAccount()
const { preference, setPreference } = useTheme()
const { serviceStatus, refresh } = useServiceHealth()
const { pending: signingOut, message: signOutError, run } = useSubmission()
const appName = useRuntimeConfig().public.appName

const themeOptions: ReadonlyArray<{ value: ThemePreference, label: string }> = [
  { value: 'dark', label: 'Sombre' },
  { value: 'light', label: 'Clair' },
  { value: 'system', label: 'Système' },
]

const statusLabels = {
  checking: 'Vérification…',
  ok: 'Connecté',
  degraded: 'Service dégradé',
  unreachable: 'Serveur injoignable',
} as const

const securitySummary = computed(() => {
  if (!account.value) return ''
  const parts = [
    account.value.emailVerified ? 'Email confirmé' : 'Email à confirmer',
    account.value.twoFactorEnabled ? 'Double authentification active' : 'Double authentification désactivée',
  ]
  return parts.join(' · ')
})

async function leave(): Promise<void> {
  if (await run(() => signOut())) await navigateTo('/connexion')
}
</script>

<template>
  <div class="page">
    <nav
      v-if="account"
      aria-label="Sections des réglages"
    >
      <ul class="rows">
        <li>
          <NuxtLink
            to="/reglages/profil"
            class="row"
          >
            <span class="row__text">
              <span class="row__title">Profil</span>
              <span class="row__summary">{{ account.firstName }}</span>
            </span>
            <ChevronRight
              :size="20"
              aria-hidden="true"
            />
          </NuxtLink>
        </li>
        <li>
          <NuxtLink
            to="/reglages/compte"
            class="row"
          >
            <span class="row__text">
              <span class="row__title">Compte et sécurité</span>
              <span
                class="row__summary"
                data-testid="security-summary"
              >{{ securitySummary }}</span>
            </span>
            <ChevronRight
              :size="20"
              aria-hidden="true"
            />
          </NuxtLink>
        </li>
      </ul>
    </nav>

    <UiCard title="Apparence">
      <fieldset class="choice">
        <legend class="choice__legend">
          Thème
        </legend>
        <label
          v-for="option in themeOptions"
          :key="option.value"
          class="choice__option"
        >
          <input
            type="radio"
            name="theme"
            :value="option.value"
            :checked="preference === option.value"
            @change="setPreference(option.value)"
          >
          <span>{{ option.label }}</span>
        </label>
      </fieldset>
    </UiCard>

    <UiCard title="À propos">
      <dl class="about">
        <dt>Application</dt>
        <dd>{{ appName }}</dd>
        <dt>Serveur</dt>
        <dd>
          <span
            class="status"
            :class="`status--${serviceStatus}`"
            data-testid="service-status"
          >{{ statusLabels[serviceStatus] }}</span>
        </dd>
      </dl>
      <UiButton
        v-if="serviceStatus === 'unreachable' || serviceStatus === 'degraded'"
        variant="secondary"
        @click="refresh()"
      >
        Réessayer
      </UiButton>
    </UiCard>

    <template v-if="account">
      <UiAlert
        v-if="signOutError"
        tone="error"
      >
        {{ signOutError }} Tu es toujours connecté sur cet appareil.
      </UiAlert>
      <UiButton
        variant="secondary"
        :loading="signingOut"
        @click="leave"
      >
        Se déconnecter
      </UiButton>
    </template>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-width: var(--reading-max-width);
}

.rows {
  margin: 0;
  padding: 0;
  list-style: none;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.rows li + li {
  border-top: 1px solid var(--color-border);
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  min-height: 3.5rem;
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  text-decoration: none;
}

.row:focus-visible {
  outline-offset: -2px;
  border-radius: var(--radius-lg);
}

.row__text {
  display: flex;
  flex-direction: column;
}

.row__title {
  font-weight: 600;
}

.row__summary {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.choice {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  border: 0;
}

.choice__legend {
  margin-bottom: var(--space-2);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.choice__option {
  position: relative;
  display: grid;
  place-items: center;
  min-height: var(--tap-target);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-raised);
  font-weight: 600;
  cursor: pointer;
}

.choice__option input {
  position: absolute;
  opacity: 0;
  inset: 0;
  margin: 0;
  cursor: pointer;
}

.choice__option:has(input:checked) {
  border-color: var(--color-accent);
  background: var(--color-accent-soft);
  color: var(--color-accent-text);
}

.choice__option:has(input:focus-visible) {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.about {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: var(--space-2) var(--space-4);
  margin: 0;
}

.about dt {
  color: var(--color-text-muted);
}

.about dd {
  margin: 0;
}

.status {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
}

.status::before {
  content: '';
  width: 0.625rem;
  height: 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.status--ok::before {
  background: var(--color-success);
}

.status--degraded::before,
.status--unreachable::before {
  background: var(--color-danger);
}
</style>
