<script setup lang="ts">
import { Search, Settings } from 'lucide-vue-next'
import { HEADER_BUTTON } from '~/components/ui/classes'
import { PAGE_HEADING_KEY } from '~/composables/usePageHeading'
import { settingsNavigation } from '~/features/shell/navigation'

const route = useRoute()
const appName = useRuntimeConfig().public.appName
const heading = useState<string | null>(PAGE_HEADING_KEY, () => null)
const title = computed(() => heading.value ?? route.meta.title ?? appName)
// The shortcut the device knows: ⌘K on Apple devices, Ctrl K elsewhere.
const shortcut = computed(() => (import.meta.client && /Mac|iPhone|iPad/.test(navigator.userAgent) ? '⌘K' : 'Ctrl K'))

// The document title changes on every navigation, which NuxtRouteAnnouncer reads out.
useHead({
  title: () => route.meta.title,
  titleTemplate: pageTitle => (pageTitle ? `${pageTitle} · ${appName}` : appName),
})

// The search palette is loaded the first time it is asked for (⌘K / Ctrl+K or the button).
const searching = ref(false)
const searchLoaded = ref(false)
function openSearch(): void {
  searchLoaded.value = true
  searching.value = true
}
useEventListener('keydown', (event) => {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault()
    openSearch()
  }
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
        <!-- Words break at syllables (lang fr), never in the middle of one, where the header is narrow. -->
        <h1
          id="page-title"
          class="min-w-0 text-xl font-bold tracking-tight wrap-break-word hyphens-auto md:text-2xl"
        >
          {{ title }}
        </h1>
        <div class="flex shrink-0 items-center gap-2">
          <button
            type="button"
            :class="`${HEADER_BUTTON} md:hidden`"
            aria-haspopup="dialog"
            aria-keyshortcuts="Control+K Meta+K"
            aria-label="Rechercher"
            title="Rechercher"
            @click="openSearch"
          >
            <Search
              :size="20"
              aria-hidden="true"
            />
          </button>
          <!-- From md, the shortcut shows. -->
          <button
            type="button"
            class="hidden min-h-tap cursor-pointer items-center gap-2 rounded-full border border-line bg-surface px-4 text-sm text-muted transition-colors hover:border-accent hover:text-ink md:inline-flex"
            aria-haspopup="dialog"
            aria-keyshortcuts="Control+K Meta+K"
            @click="openSearch"
          >
            <Search
              :size="18"
              aria-hidden="true"
            />
            Rechercher…
            <kbd class="rounded-sm border border-line px-1.5 font-sans text-xs">{{ shortcut }}</kbd>
          </button>
          <ShellQuickActions />
          <ShellNotificationBell />
          <!-- From md, settings sit at the bottom of the rail. -->
          <NuxtLink
            :to="settingsNavigation.to"
            :class="`${HEADER_BUTTON} md:hidden`"
            :aria-current="route.path.startsWith(settingsNavigation.to) ? 'page' : undefined"
            :aria-label="settingsNavigation.label"
            :title="settingsNavigation.label"
          >
            <Settings
              :size="20"
              aria-hidden="true"
            />
          </NuxtLink>
        </div>
      </header>
      <main
        id="main"
        class="mx-auto flex max-w-content flex-col gap-4 pt-2 px-gutter pb-24 focus:outline-none md:pb-8"
        tabindex="-1"
        aria-labelledby="page-title"
      >
        <ShellConnectivityBanner />
        <ShellDeletionCancelledNotice />
        <slot />
      </main>
    </div>
    <ShellUpdatePrompt />
    <LazyShellSearchPalette
      v-if="searchLoaded"
      v-model:open="searching"
    />
  </div>
</template>
