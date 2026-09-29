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
    class="grid min-h-dvh items-start justify-items-center pt-safe-8 px-gutter pb-safe-8 md:place-items-center"
  >
    <main
      id="main"
      class="flex w-full max-w-104 flex-col gap-4 md:rounded-lg md:border md:border-line md:bg-surface md:p-8 md:shadow-raised"
      aria-labelledby="page-title"
    >
      <p class="font-bold tracking-snug text-accent-ink">
        {{ appName }}
      </p>
      <h1
        id="page-title"
        class="text-2xl font-bold tracking-tight"
      >
        {{ route.meta.title }}
      </h1>
      <ShellConnectivityBanner />
      <slot />
    </main>
  </div>
</template>
