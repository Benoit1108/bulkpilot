<script setup lang="ts">
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
    class="external"
  >
    <p class="external__separator">
      <span>ou</span>
    </p>
    <a
      v-for="provider in data.providers"
      :key="provider"
      class="external__button"
      :href="href(provider)"
    >
      {{ labels[provider] ?? provider }}
    </a>
  </div>
</template>

<style scoped>
.external {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.external__separator {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.external__separator::before,
.external__separator::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--color-border);
}

.external__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: var(--tap-target);
  padding: 0 var(--space-4);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-text);
  font-weight: 600;
  text-decoration: none;
}

.external__button:hover {
  border-color: var(--color-accent);
}
</style>
