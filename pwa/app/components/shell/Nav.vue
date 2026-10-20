<script setup lang="ts">
import { Dumbbell } from 'lucide-vue-next'
import { activePage, isActiveSection, primaryNavigation, settingsNavigation, type NavigationItem } from '~/features/shell/navigation'

const route = useRoute()
const appName = useRuntimeConfig().public.appName

const active = (item: NavigationItem) => isActiveSection(item.to, route.path)
/**
 * A section with pages is « true » (current section) and its page is « page »: one current page only.
 * A section without pages is the page itself.
 */
const current = (item: NavigationItem) => (active(item) ? (item.pages ? 'true' : 'page') : undefined)
const openSection = computed(() => primaryNavigation.find(item => item.pages && active(item)))
const currentPage = computed(() => (openSection.value?.pages ? activePage(openSection.value.pages, route.path) : null))
</script>

<template>
  <!--
    Phones: bottom bar with the five sections (settings live in the header).
    From md: vertical rail on the left, settings pinned at the bottom. From xl: labelled sidebar, the
    open section listing its pages.
  -->
  <nav
    class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-surface/92 pb-safe-0 backdrop-blur-md md:inset-y-0 md:right-auto md:flex md:w-nav-rail md:flex-col md:overflow-y-auto md:border-t-0 md:border-r md:pt-safe-4 md:pb-safe-4 md:pl-safe-0 xl:w-nav-sidebar xl:px-3"
    aria-label="Navigation principale"
  >
    <p
      class="hidden items-center gap-2.5 px-2.5 pb-5 text-lg font-bold tracking-snug xl:flex"
      aria-hidden="true"
    >
      <span class="grid size-7 place-items-center rounded-sm bg-accent-fill text-accent-contrast">
        <Dumbbell :size="18" />
      </span>
      {{ appName }}
    </p>
    <ul class="mx-auto grid h-bottom-nav max-w-reading grid-cols-5 md:mx-0 md:flex md:h-auto md:max-w-none md:flex-1 md:flex-col md:gap-2 md:px-2 xl:gap-0 xl:px-0">
      <li
        v-for="item in [...primaryNavigation, settingsNavigation]"
        :key="item.to"
        :class="{ 'hidden md:mt-auto md:block xl:border-t xl:border-line xl:pt-2': item === settingsNavigation }"
      >
        <!-- The active section is not told by colour alone: heavier label, and a tint on the rail and the sidebar. -->
        <NuxtLink
          :to="item.to"
          class="group flex h-full flex-col items-center justify-center gap-0.5 rounded-md text-xs font-medium text-muted no-underline transition-colors hover:text-ink focus-visible:-outline-offset-4 aria-[current]:font-bold aria-[current]:text-accent-ink md:h-auto md:py-2 xl:min-h-tap xl:flex-row xl:justify-start xl:gap-2.5 xl:px-2.5 xl:py-1 xl:text-callout xl:text-ink xl:hover:bg-surface-raised xl:aria-[current]:text-ink xl:aria-[current=page]:bg-accent-soft"
          :aria-current="current(item)"
        >
          <span class="grid place-items-center rounded-full px-4 py-0.5 transition-colors md:group-aria-[current]:bg-accent-soft xl:p-0 xl:text-accent xl:group-aria-[current]:bg-transparent">
            <component
              :is="item.icon"
              :size="22"
              class="xl:size-5"
              aria-hidden="true"
            />
          </span>
          <span>{{ item.label }}</span>
        </NuxtLink>
        <ul
          v-if="item === openSection"
          class="hidden flex-col xl:flex"
        >
          <li
            v-for="page in item.pages ?? []"
            :key="page.to"
          >
            <NuxtLink
              :to="page.to"
              class="flex min-h-tap items-center rounded-md ps-10 pe-2.5 text-sm text-muted no-underline transition-colors hover:bg-surface-raised hover:text-ink aria-[current=page]:bg-accent-soft aria-[current=page]:font-semibold aria-[current=page]:text-ink"
              :aria-current="page.to === currentPage ? 'page' : undefined"
            >
              {{ page.label }}
            </NuxtLink>
          </li>
        </ul>
      </li>
    </ul>
  </nav>
</template>
