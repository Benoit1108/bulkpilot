<script setup lang="ts">
/**
 * A tile, as in Apple Health (a dashboard widget, a section's summary): its name in the accent
 * colour, the whole card leading to what it sums up (its name is the link, stretched over the card;
 * a button inside stays a button). It loads on its own, so a failure stays in it (the others show)
 * and does not take the focus away from where the user is.
 */
defineProps<{
  title: string
  /** Where to see more (what the tile sums up). */
  to?: string
  loading: boolean
  failed: boolean
  /**
   * The room kept while loading, about the height of the tile once loaded — its empty state for a
   * new account: the tiles below do not jump when the data arrives (layout shift). Literal
   * classes, so Tailwind sees them.
   */
  reserve?: 'min-h-28' | 'min-h-40'
}>()
const emit = defineEmits<{ retry: [] }>()
const titleId = useId()
</script>

<template>
  <section
    class="relative flex flex-col gap-2 rounded-lg bg-surface p-4"
    :aria-labelledby="titleId"
    :aria-busy="loading || undefined"
  >
    <h2
      :id="titleId"
      class="text-callout font-semibold text-accent-ink"
    >
      <NuxtLink
        v-if="to"
        :to="to"
        class="text-inherit no-underline after:absolute after:inset-0 after:rounded-lg hover:underline focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-accent"
      >{{ title }}</NuxtLink>
      <template v-else>
        {{ title }}
      </template>
    </h2>
    <!-- Above the stretched link: what is inside can be read and its own buttons pressed. -->
    <div class="pointer-events-none relative flex flex-col gap-2 [&_:is(a,button,input,select,textarea,label,summary,[tabindex])]:pointer-events-auto">
      <UiLoadError
        v-if="failed"
        :focus-on-mount="false"
        @retry="emit('retry')"
      />
      <p
        v-else-if="loading"
        class="text-sm text-muted"
        :class="reserve ?? 'min-h-28'"
      >
        Chargement…
      </p>
      <slot v-else />
    </div>
  </section>
</template>
