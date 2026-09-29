<script setup lang="ts">
import { isActiveSection, primaryNavigation, settingsNavigation } from '~/features/shell/navigation'

const route = useRoute()
const appName = useRuntimeConfig().public.appName
</script>

<template>
  <!--
    Phones: bottom bar with the five sections (settings live in the header).
    From md: vertical rail on the left, settings pinned at the bottom. From xl: labelled sidebar.
  -->
  <nav
    class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-surface/92 pb-safe-0 backdrop-blur-md md:inset-y-0 md:right-auto md:flex md:w-nav-rail md:flex-col md:border-t-0 md:border-r md:pt-safe-4 md:pb-safe-4 md:pl-safe-0 xl:w-nav-sidebar"
    aria-label="Navigation principale"
  >
    <p
      class="hidden px-6 pb-6 text-lg font-bold tracking-snug xl:block"
      aria-hidden="true"
    >
      {{ appName }}
    </p>
    <ul class="mx-auto my-0 grid h-bottom-nav max-w-reading list-none grid-cols-5 p-0 md:flex md:h-auto md:max-w-none md:flex-1 md:flex-col md:gap-2 md:px-2 xl:px-3">
      <li
        v-for="item in [...primaryNavigation, settingsNavigation]"
        :key="item.to"
        :class="{ 'hidden md:mt-auto md:block': item === settingsNavigation }"
      >
        <!-- The active section is not told by colour alone: filled pill + heavier label. -->
        <NuxtLink
          :to="item.to"
          class="group flex h-full flex-col items-center justify-center gap-0.5 rounded-md text-xs font-medium text-muted no-underline transition-colors hover:text-ink focus-visible:-outline-offset-4 aria-[current=page]:font-bold aria-[current=page]:text-accent-ink md:h-auto md:py-2 xl:min-h-tap xl:flex-row xl:justify-start xl:gap-3 xl:px-2 xl:py-1 xl:text-base"
          :aria-current="isActiveSection(item.to, route.path) ? 'page' : undefined"
        >
          <span class="grid place-items-center rounded-full px-4 py-0.5 transition-colors group-aria-[current=page]:bg-accent-soft xl:px-3 xl:py-1">
            <component
              :is="item.icon"
              :size="22"
              aria-hidden="true"
            />
          </span>
          <span>{{ item.label }}</span>
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
