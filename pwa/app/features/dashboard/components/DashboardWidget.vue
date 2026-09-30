<script setup lang="ts">
import { TEXT_LINK } from '~/components/ui/classes'

/**
 * One widget of the dashboard: loads on its own, so a failure stays in it (the others show) and
 * does not take the focus away from where the user is.
 */
defineProps<{
  title: string
  /** Where to see more (the section the widget sums up). */
  to?: string
  linkLabel?: string
  loading: boolean
  failed: boolean
}>()
const emit = defineEmits<{ retry: [] }>()
</script>

<template>
  <UiCard
    :title="title"
    :aria-busy="loading || undefined"
  >
    <template
      v-if="to"
      #actions
    >
      <NuxtLink
        :to="to"
        :class="`${TEXT_LINK} text-sm`"
      >{{ linkLabel ?? 'Voir' }}<span class="sr-only"> ({{ title }})</span></NuxtLink>
    </template>
    <UiLoadError
      v-if="failed"
      :focus-on-mount="false"
      @retry="emit('retry')"
    />
    <p
      v-else-if="loading"
      class="text-sm text-muted"
    >
      Chargement…
    </p>
    <slot v-else />
  </UiCard>
</template>
