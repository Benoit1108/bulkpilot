<script setup lang="ts">
const props = withDefaults(defineProps<{
  tone?: 'error' | 'success' | 'info'
  /** False for an error that comes on its own (a background sync): announced, but the focus stays where the user is. */
  focusOnMount?: boolean
}>(), { tone: 'info', focusOnMount: true })

const TONES = {
  error: 'border-l-danger',
  success: 'border-l-success',
  info: 'border-l-accent',
} as const

// A new error takes the focus: keyboard and screen-reader users land on what went wrong,
// instead of on <body> after the submit button re-rendered.
const root = ref<HTMLElement | null>(null)
onMounted(() => {
  if (props.tone === 'error' && props.focusOnMount) root.value?.focus()
})
</script>

<template>
  <!-- Errors interrupt (alert); confirmations are announced politely (status). -->
  <div
    ref="root"
    class="rounded-md border border-l-4 border-line bg-surface-raised px-4 py-3 text-sm [&_a]:font-semibold [&_a]:text-accent-ink"
    :class="TONES[tone]"
    :role="tone === 'error' ? 'alert' : 'status'"
    :tabindex="tone === 'error' ? -1 : undefined"
  >
    <slot />
  </div>
</template>
