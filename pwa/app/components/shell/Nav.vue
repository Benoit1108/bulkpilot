<script setup lang="ts">
import { isActiveSection, primaryNavigation, settingsNavigation } from '~/features/shell/navigation'

const route = useRoute()
const appName = useRuntimeConfig().public.appName
</script>

<template>
  <nav
    class="nav"
    aria-label="Navigation principale"
  >
    <p
      class="nav__brand"
      aria-hidden="true"
    >
      {{ appName }}
    </p>
    <ul class="nav__list">
      <li
        v-for="item in primaryNavigation"
        :key="item.to"
      >
        <NuxtLink
          :to="item.to"
          class="nav__link"
          :aria-current="isActiveSection(item.to, route.path) ? 'page' : undefined"
        >
          <span class="nav__icon">
            <component
              :is="item.icon"
              :size="22"
              aria-hidden="true"
            />
          </span>
          <span class="nav__label">{{ item.label }}</span>
        </NuxtLink>
      </li>
      <li class="nav__settings">
        <NuxtLink
          :to="settingsNavigation.to"
          class="nav__link"
          :aria-current="isActiveSection(settingsNavigation.to, route.path) ? 'page' : undefined"
        >
          <span class="nav__icon">
            <component
              :is="settingsNavigation.icon"
              :size="22"
              aria-hidden="true"
            />
          </span>
          <span class="nav__label">{{ settingsNavigation.label }}</span>
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>

<style scoped>
/* Phones: bottom bar with the five sections (settings live in the header). */
.nav {
  position: fixed;
  inset: auto 0 0;
  z-index: 10;
  padding-bottom: env(safe-area-inset-bottom);
  background: color-mix(in srgb, var(--color-surface) 92%, transparent);
  border-top: 1px solid var(--color-border);
  backdrop-filter: blur(12px);
}

.nav__brand,
.nav__settings {
  display: none;
}

.nav__list {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  max-width: var(--reading-max-width);
  height: var(--bottom-nav-height);
  margin: 0 auto;
  padding: 0;
  list-style: none;
}

.nav__link {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  height: 100%;
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
  font-weight: 500;
  text-decoration: none;
  transition: color var(--transition-fast);
}

.nav__link:focus-visible {
  outline-offset: -4px;
}

.nav__icon {
  display: grid;
  place-items: center;
  padding: 2px var(--space-4);
  border-radius: var(--radius-full);
  transition: background-color var(--transition-fast);
}

/* The active section is not told by colour alone: filled pill + heavier label. */
.nav__link[aria-current='page'] {
  color: var(--color-accent-text);
  font-weight: 700;
}

.nav__link[aria-current='page'] .nav__icon {
  background: var(--color-accent-soft);
}

.nav__link:hover {
  color: var(--color-text);
}

/* Tablets and small desktops: vertical rail on the left, settings pinned at the bottom. */
@media (min-width: 48rem) {
  .nav {
    display: flex;
    flex-direction: column;
    inset: 0 auto 0 0;
    width: var(--nav-rail-width);
    padding: calc(env(safe-area-inset-top) + var(--space-4)) 0 calc(env(safe-area-inset-bottom) + var(--space-4));
    padding-left: env(safe-area-inset-left);
    border-top: 0;
    border-right: 1px solid var(--color-border);
  }

  .nav__list {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: var(--space-2);
    height: auto;
    max-width: none;
    padding: 0 var(--space-2);
  }

  .nav__link {
    height: auto;
    padding: var(--space-2) 0;
  }

  .nav__settings {
    display: block;
    margin-top: auto;
  }
}

/* Wide screens: labelled sidebar. */
@media (min-width: 75rem) {
  .nav {
    width: var(--nav-sidebar-width);
  }

  .nav__brand {
    display: block;
    padding: 0 var(--space-5) var(--space-5);
    font-size: var(--text-lg);
    font-weight: 700;
    letter-spacing: -0.01em;
  }

  .nav__list {
    padding: 0 var(--space-3);
  }

  .nav__link {
    flex-direction: row;
    justify-content: flex-start;
    gap: var(--space-3);
    min-height: var(--tap-target);
    padding: var(--space-1) var(--space-2);
    font-size: var(--text-md);
  }

  .nav__icon {
    padding: var(--space-1) var(--space-3);
  }
}
</style>
