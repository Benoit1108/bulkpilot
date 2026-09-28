<script setup lang="ts">
import DeviceList from '~/features/identity/components/DeviceList.vue'
import EmailStatus from '~/features/identity/components/EmailStatus.vue'
import PasswordChangeForm from '~/features/identity/components/PasswordChangeForm.vue'
import TwoFactorSettings from '~/features/identity/components/TwoFactorSettings.vue'

definePageMeta({ title: 'Compte et sécurité' })

const { account } = useAccount()
</script>

<template>
  <div class="subpage">
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
          class="subpage__hint"
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

<style scoped>
.subpage {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-width: var(--reading-max-width);
}

.subpage__hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.subpage__hint a {
  color: var(--color-accent-text);
  font-weight: 600;
}
</style>
