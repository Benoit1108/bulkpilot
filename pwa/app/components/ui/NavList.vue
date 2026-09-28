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
    <ul class="ui-nav-list">
      <li
        v-for="item in items"
        :key="item.to"
      >
        <NuxtLink
          :to="item.to"
          class="ui-nav-list__row"
        >
          <span class="ui-nav-list__text">
            <span class="ui-nav-list__title">{{ item.title }}</span>
            <span
              v-if="item.summary"
              class="ui-nav-list__summary"
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

<style scoped>
.ui-nav-list {
  margin: 0;
  padding: 0;
  list-style: none;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
}

.ui-nav-list li + li {
  border-top: 1px solid var(--color-border);
}

.ui-nav-list__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  min-height: 3.5rem;
  padding: var(--space-3) var(--space-4);
  color: var(--color-text);
  text-decoration: none;
}

.ui-nav-list__row:focus-visible {
  outline-offset: -2px;
  border-radius: var(--radius-lg);
}

.ui-nav-list__text {
  display: flex;
  flex-direction: column;
}

.ui-nav-list__title {
  font-weight: 600;
}

.ui-nav-list__summary {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
