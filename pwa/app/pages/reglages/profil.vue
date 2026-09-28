<script setup lang="ts">
import ProfileForm from '~/features/identity/components/ProfileForm.vue'

definePageMeta({ title: 'Profil' })

const { account } = useAccount()
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/reglages"
      label="Réglages"
    />
    <UiCard v-if="account">
      <ProfileForm :account="account" />
    </UiCard>
  </div>
</template>
