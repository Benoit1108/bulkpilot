<script setup lang="ts">
import { Settings } from 'lucide-vue-next'
import { settingsNavigation } from '~/features/shell/navigation'

const route = useRoute()
const appName = useRuntimeConfig().public.appName
const title = computed(() => route.meta.title ?? appName)

// The document title changes on every navigation, which NuxtRouteAnnouncer reads out.
useHead({
  title: () => route.meta.title,
  titleTemplate: pageTitle => (pageTitle ? `${pageTitle} · ${appName}` : appName),
})
</script>

<template>
  <div class="min-h-dvh pt-safe-0 pb-nav md:pb-safe-0 md:pl-nav-rail xl:pl-nav-sidebar">
    <a
      href="#main"
      class="absolute top-3 left-3 z-40 translate-y-[-200%] rounded-sm bg-accent-fill px-4 py-2 font-semibold text-accent-contrast focus:translate-y-0"
    >Aller au contenu</a>
    <ShellNav />
    <div>
      <header
        class="mx-auto flex max-w-content items-center justify-between gap-3 pt-4 px-gutter pb-2 md:pt-8"
      >
        <h1
          id="page-title"
          class="text-2xl font-bold tracking-tight"
        >
          {{ title }}
        </h1>
        <!-- From md, settings sit at the bottom of the rail. -->
        <NuxtLink
          :to="settingsNavigation.to"
          class="grid size-tap place-items-center rounded-full border border-line bg-surface text-muted transition-colors hover:border-accent hover:text-ink aria-[current=page]:border-accent aria-[current=page]:bg-accent-soft aria-[current=page]:text-accent-ink md:hidden"
          :aria-current="route.path.startsWith(settingsNavigation.to) ? 'page' : undefined"
          :aria-label="settingsNavigation.label"
          :title="settingsNavigation.label"
        >
          <Settings
            :size="20"
            aria-hidden="true"
          />
        </NuxtLink>
      </header>
      <main
        id="main"
        class="mx-auto flex max-w-content flex-col gap-4 pt-2 px-gutter pb-8 focus:outline-none"
        tabindex="-1"
        aria-labelledby="page-title"
      >
        <ShellConnectivityBanner />
        <slot />
      </main>
    </div>
    <ShellUpdatePrompt />
  </div>
</template>
