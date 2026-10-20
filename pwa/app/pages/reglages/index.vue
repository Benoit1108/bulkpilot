<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'
import type { ThemePreference } from '~/features/shell/theme'
import { useServiceHealth } from '~/features/system/health'

definePageMeta({ title: 'Réglages' })

const { account, signOut } = useAccount()
const { preference, setPreference } = useTheme()
const { serviceStatus, refresh } = useServiceHealth()
const { pending: signingOut, message: signOutError, run } = useSubmission()
const appName = useRuntimeConfig().public.appName

const themeOptions: ReadonlyArray<{ value: ThemePreference, label: string }> = [
  { value: 'system', label: 'Système' },
  { value: 'light', label: 'Clair' },
  { value: 'dark', label: 'Sombre' },
]

const statusLabels = {
  checking: 'Vérification…',
  ok: 'Connecté',
  degraded: 'Service dégradé',
  unreachable: 'Serveur injoignable',
} as const

// The dot next to the server status; the label says it too, colour is never alone.
const STATUS_DOTS = {
  checking: 'before:bg-muted',
  ok: 'before:bg-success',
  degraded: 'before:bg-danger',
  unreachable: 'before:bg-danger',
} as const

const securitySummary = computed(() => {
  if (!account.value) return ''
  const parts = [
    account.value.emailVerified ? 'Email confirmé' : 'Email à confirmer',
    account.value.twoFactorEnabled ? 'Double authentification active' : 'Double authentification désactivée',
  ]
  return parts.join(' · ')
})

const sections = computed(() => account.value
  ? [
      { to: '/reglages/profil', title: 'Profil', summary: account.value.firstName },
      { to: '/reglages/compte', title: 'Compte et sécurité', summary: securitySummary.value, summaryTestId: 'security-summary' },
      { to: '/coach/reglages', title: 'Coach', summary: 'Le ton du Coach et le rythme visé' },
      { to: '/notifications/reglages', title: 'Notifications', summary: 'Ce qui t’est signalé et les horaires silencieux' },
      { to: '/reglages/donnees', title: 'Mes données', summary: 'Exporter tout ou partie de tes données' },
      { to: '/reglages/activite', title: 'Activité du compte', summary: 'Connexions et changements importants' },
    ]
  : [])

async function leave(): Promise<void> {
  if (await run(() => signOut())) await navigateTo('/connexion')
}
</script>

<template>
  <UiPage>
    <UiNavList
      v-if="account"
      label="Sections des réglages"
      :items="sections"
    />

    <UiCard title="Apparence">
      <UiSegmented
        :model-value="preference"
        legend="Thème"
        name="theme"
        :options="themeOptions"
        @update:model-value="setPreference"
      />
    </UiCard>

    <UiCard title="À propos">
      <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2">
        <dt class="text-muted">
          Application
        </dt>
        <dd class="m-0">
          {{ appName }}
        </dd>
        <dt class="text-muted">
          Serveur
        </dt>
        <dd class="m-0">
          <span
            class="inline-flex items-center gap-2 before:size-2.5 before:rounded-full"
            :class="STATUS_DOTS[serviceStatus]"
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
      <NuxtLink
        to="/reglages/credits"
        :class="TEXT_LINK"
      >
        Crédits et licences
      </NuxtLink>
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
  </UiPage>
</template>
