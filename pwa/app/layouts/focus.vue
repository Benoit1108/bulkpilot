<script setup lang="ts">
const route = useRoute()
const appName = useRuntimeConfig().public.appName

useHead({
  title: () => route.meta.title,
  titleTemplate: pageTitle => (pageTitle ? `${pageTitle} · ${appName}` : appName),
})
</script>

<template>
  <div class="focus">
    <!-- Training mode (spec §42): the whole screen for the workout, no navigation around it. -->
    <main
      id="main"
      class="focus__main"
      tabindex="-1"
      aria-labelledby="page-title"
    >
      <slot />
    </main>
    <ShellUpdatePrompt />
  </div>
</template>

<style scoped>
.focus {
  min-height: 100dvh;
  padding: calc(env(safe-area-inset-top) + var(--space-3)) max(var(--space-4), env(safe-area-inset-right)) calc(env(safe-area-inset-bottom) + var(--space-4)) max(var(--space-4), env(safe-area-inset-left));
  background: var(--color-bg);
}

.focus__main {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-width: 36rem;
  margin: 0 auto;
}

.focus__main:focus {
  outline: none;
}
</style>
