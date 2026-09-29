<script setup lang="ts">
definePageMeta({ title: 'Confirmation de l’email', layout: 'auth', public: true })

const route = useRoute()
const router = useRouter()
const api = useApi()
const { account, load } = useAccount()
const { message, run } = useSubmission()
const status = ref<'pending' | 'done' | 'failed'>('pending')

onMounted(async () => {
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  // The secret is not kept in the address bar or history.
  void router.replace({ query: {} })
  const ok = await run(() => api.post('/api/auth/email-verification', { token }))
  status.value = ok ? 'done' : 'failed'
  if (ok && account.value) await load()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <p
      v-if="status === 'pending'"
      role="status"
    >
      Confirmation en cours…
    </p>
    <UiAlert
      v-else-if="status === 'done'"
      tone="success"
    >
      Ton adresse email est confirmée.
    </UiAlert>
    <UiAlert
      v-else
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiLinkRow v-if="status === 'failed' && account">
      <NuxtLink to="/reglages/compte#email">Recevoir un nouveau lien</NuxtLink>
    </UiLinkRow>
    <UiLinkRow v-else-if="status !== 'pending'">
      <NuxtLink :to="account ? '/' : '/connexion'">
        {{ account ? 'Aller à l’accueil' : 'Aller à la connexion' }}
      </NuxtLink>
    </UiLinkRow>
  </div>
</template>
