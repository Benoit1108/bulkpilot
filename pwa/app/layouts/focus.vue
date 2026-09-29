<script setup lang="ts">
const route = useRoute()
const appName = useRuntimeConfig().public.appName

useHead({
  title: () => route.meta.title,
  titleTemplate: pageTitle => (pageTitle ? `${pageTitle} · ${appName}` : appName),
})
</script>

<template>
  <div
    class="min-h-dvh bg-canvas pt-safe-3 px-gutter pb-safe-4"
  >
    <!-- Training mode (spec §42): the whole screen for the workout, no navigation around it. -->
    <main
      id="main"
      class="mx-auto flex max-w-xl flex-col gap-4 focus:outline-none"
      tabindex="-1"
      aria-labelledby="page-title"
    >
      <slot />
    </main>
    <ShellUpdatePrompt />
  </div>
</template>
