<script setup lang="ts">
import { buttonClass } from '~/components/ui/classes'

const props = defineProps<{ redirect?: string }>()

const api = useApi()
const { data } = useAsyncData('identity-providers', () => api.get('/api/auth/providers'))

const labels: Record<string, string> = {
  google: 'Continuer avec Google',
  apple: 'Continuer avec Apple',
  fake: 'Compte de démonstration (dev)',
}

// A full-page navigation: the provider's pages are not part of the SPA.
function href(provider: string): string {
  const query = new URLSearchParams({ redirect: props.redirect ?? '/' })
  return `/api/auth/oauth/${encodeURIComponent(provider)}/start?${query}`
}
</script>

<template>
  <div
    v-if="data?.providers.length"
    class="flex flex-col gap-3"
  >
    <p class="flex items-center gap-3 text-sm text-muted before:h-px before:flex-1 before:bg-line after:h-px after:flex-1 after:bg-line">
      <span>ou</span>
    </p>
    <a
      v-for="provider in data.providers"
      :key="provider"
      :class="buttonClass('secondary')"
      :href="href(provider)"
    >
      {{ labels[provider] ?? provider }}
    </a>
  </div>
</template>
