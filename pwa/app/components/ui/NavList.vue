<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'

export interface NavListItem {
  to: string
  title: string
  summary?: string
  /** Test hook on the summary line. */
  summaryTestId?: string
}

defineProps<{ label: string, items: readonly NavListItem[] }>()
</script>

<template>
  <nav :aria-label="label">
    <ul class="m-0 list-none divide-y divide-line rounded-lg border border-line bg-surface p-0">
      <li
        v-for="item in items"
        :key="item.to"
      >
        <NuxtLink
          :to="item.to"
          class="flex min-h-14 items-center justify-between gap-3 px-4 py-3 text-ink no-underline focus-visible:rounded-lg focus-visible:-outline-offset-2"
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
            aria-hidden="true"
          />
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
