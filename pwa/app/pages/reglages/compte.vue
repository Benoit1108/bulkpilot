<script setup lang="ts">
import DeviceList from '~/features/identity/components/DeviceList.vue'
import EmailStatus from '~/features/identity/components/EmailStatus.vue'
import PasswordChangeForm from '~/features/identity/components/PasswordChangeForm.vue'
import TwoFactorSettings from '~/features/identity/components/TwoFactorSettings.vue'

definePageMeta({ title: 'Compte et sécurité' })

const { account } = useAccount()
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/reglages"
      label="Réglages"
    />
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
    </template>
  </div>
</template>
