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
  <div class="shell">
    <a
      href="#main"
      class="shell__skip-link"
    >Aller au contenu</a>
    <ShellNav />
    <div class="shell__body">
      <header class="shell__header">
        <h1
          id="page-title"
          class="shell__title"
        >
          {{ title }}
        </h1>
        <NuxtLink
          :to="settingsNavigation.to"
          class="shell__settings"
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
        class="shell__main"
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

<style scoped>
.shell {
  --gutter-left: max(var(--space-4), env(safe-area-inset-left));
  --gutter-right: max(var(--space-4), env(safe-area-inset-right));

  min-height: 100dvh;
  padding-top: env(safe-area-inset-top);
  padding-bottom: calc(var(--bottom-nav-height) + env(safe-area-inset-bottom));
}

.shell__skip-link {
  position: absolute;
  top: var(--space-3);
  left: var(--space-3);
  z-index: 40;
  padding: var(--space-2) var(--space-4);
  background: var(--color-accent-fill);
  color: var(--color-accent-contrast);
  border-radius: var(--radius-sm);
  font-weight: 600;
  transform: translateY(-200%);
}

.shell__skip-link:focus {
  transform: none;
}

.shell__header,
.shell__main {
  max-width: var(--content-max-width);
  margin: 0 auto;
  padding-inline: var(--gutter-left) var(--gutter-right);
}

.shell__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding-block: var(--space-4) var(--space-2);
}

.shell__title {
  font-size: var(--text-2xl);
  font-weight: 700;
  letter-spacing: -0.02em;
}

.shell__settings {
  display: grid;
  place-items: center;
  width: var(--tap-target);
  height: var(--tap-target);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-full);
  background: var(--color-surface);
  color: var(--color-text-muted);
  transition: color var(--transition-fast), border-color var(--transition-fast);
}

.shell__settings[aria-current='page'] {
  border-color: var(--color-accent);
  background: var(--color-accent-soft);
  color: var(--color-accent-text);
}

.shell__settings:hover {
  color: var(--color-text);
  border-color: var(--color-accent);
}

.shell__main {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  padding-block: var(--space-2) var(--space-6);
}

.shell__main:focus {
  outline: none;
}

@media (min-width: 48rem) {
  .shell {
    padding-bottom: env(safe-area-inset-bottom);
    padding-left: var(--nav-rail-width);
  }

  .shell__header {
    padding-top: var(--space-6);
  }

  /* Settings sit at the bottom of the rail. */
  .shell__settings {
    display: none;
  }
}

@media (min-width: 75rem) {
  .shell {
    padding-left: var(--nav-sidebar-width);
  }
}
</style>
