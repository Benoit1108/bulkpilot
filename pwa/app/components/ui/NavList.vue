<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'

export interface NavListItem {
  to: string
  title: string
  summary?: string
  /** Test hook on the summary line. */
  summaryTestId?: string
  /** What `keep` knows the item by (a paged list moves the focus to the first item it added). */
  id?: string
}

const props = defineProps<{
  label: string
  items: readonly NavListItem[]
  /** Paged lists (usePagedList): each item's element, focusable from script. */
  keep?: (id: string, element: unknown) => void
}>()
/** The item's element for `keep`, when the list keeps them. */
const keeper = (item: NavListItem) => (props.keep && item.id ? (element: unknown) => props.keep!(item.id!, element) : undefined)
</script>

<template>
  <nav :aria-label="label">
    <ul class="divide-y divide-line overflow-hidden rounded-lg bg-surface">
      <li
        v-for="item in items"
        :key="item.to"
        :ref="keeper(item)"
        :tabindex="keep ? -1 : undefined"
      >
        <NuxtLink
          :to="item.to"
          class="flex min-h-14 items-center justify-between gap-3 px-4 py-3 text-ink no-underline transition-colors hover:bg-surface-raised focus-visible:rounded-lg focus-visible:-outline-offset-2"
        >
          <span class="flex flex-col">
            <span class="font-semibold">{{ item.title }}</span>
            <span
              v-if="item.summary"
              class="text-sm text-muted"
              :data-testid="item.summaryTestId"
            >{{ item.summary }}</span>
          </span>
          <ChevronRight
            :size="20"
            class="shrink-0 text-muted"
            aria-hidden="true"
          />
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
