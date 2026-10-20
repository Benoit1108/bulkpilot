<script setup lang="ts">
import { daysText, useDataPolicy } from '~/features/data/policy'
import DeviceList from '~/features/identity/components/DeviceList.vue'
import EmailStatus from '~/features/identity/components/EmailStatus.vue'
import PasswordChangeForm from '~/features/identity/components/PasswordChangeForm.vue'
import TwoFactorSettings from '~/features/identity/components/TwoFactorSettings.vue'

definePageMeta({ title: 'Compte et sécurité' })

const { account } = useAccount()
const { data: policy } = useDataPolicy()
const erasure = computed(() => `Ton compte est fermé tout de suite puis effacé ${policy.value ? `${daysText(policy.value.deletionDelayDays)} plus tard` : 'quelques jours plus tard'}, avec toutes tes données.`)
</script>

<template>
  <UiPage layout="columns">
    <template #top>
      <ShellBackLink
        to="/reglages"
        label="Réglages"
      />
    </template>
    <template v-if="account">
      <UiCard
        id="email"
        title="Email"
      >
        <EmailStatus :account="account" />
      </UiCard>
      <UiCard title="Mot de passe">
        <PasswordChangeForm v-if="account.hasPassword" />
        <p
          v-else
          class="text-sm text-muted [&_a]:font-semibold [&_a]:text-accent-ink"
        >
          Tu te connectes avec un service externe. Pour ajouter un mot de passe, utilise
          <NuxtLink to="/mot-de-passe-oublie">Mot de passe oublié</NuxtLink> avec ton adresse email.
        </p>
      </UiCard>
      <UiCard title="Double authentification">
        <TwoFactorSettings :account="account" />
      </UiCard>
      <UiCard title="Appareils connectés">
        <DeviceList />
      </UiCard>
      <UiCard title="Supprimer mon compte">
        <p class="text-sm text-muted">
          {{ erasure }}
        </p>
        <UiLinkButton
          to="/reglages/suppression"
          variant="secondary"
        >
          Supprimer mon compte
        </UiLinkButton>
      </UiCard>
    </template>
  </UiPage>
</template>
