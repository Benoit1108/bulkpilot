<script setup lang="ts">
const route = useRoute()
const appName = useRuntimeConfig().public.appName

useHead({
  title: () => route.meta.title,
  titleTemplate: pageTitle => (pageTitle ? `${pageTitle} · ${appName}` : appName),
})
</script>

<template>
  <div class="auth">
    <main
      id="main"
      class="auth__main"
      aria-labelledby="page-title"
    >
      <p class="auth__brand">
        {{ appName }}
      </p>
      <h1
        id="page-title"
        class="auth__title"
      >
        {{ route.meta.title }}
      </h1>
      <ShellConnectivityBanner />
      <slot />
    </main>
  </div>
</template>

<style scoped>
.auth {
  display: grid;
  place-items: start center;
  min-height: 100dvh;
  padding: calc(env(safe-area-inset-top) + var(--space-6)) max(var(--space-4), env(safe-area-inset-right)) calc(env(safe-area-inset-bottom) + var(--space-6)) max(var(--space-4), env(safe-area-inset-left));
}

.auth__main {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  width: 100%;
  max-width: 26rem;
}

.auth__brand {
  color: var(--color-accent-text);
  font-weight: 700;
  letter-spacing: -0.01em;
}

.auth__title {
  font-size: var(--text-2xl);
  font-weight: 700;
  letter-spacing: -0.02em;
}

@media (min-width: 48rem) {
  .auth {
    place-items: center;
  }

  .auth__main {
    padding: var(--space-6);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-raised);
  }
}
</style>
