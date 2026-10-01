<script setup lang="ts">
/**
 * After a sign-in that cancelled the account's planned deletion (spec §103): said once. The
 * sentence goes to a live region mounted beforehand (one inserted with its text is not always
 * read); closing the notice gives the focus to the page.
 */
const { deletionCancelled } = useAccount()
const spoken = ref('')
watch(deletionCancelled, async (cancelled) => {
  spoken.value = ''
  if (!cancelled) return
  await nextTick()
  spoken.value = 'La suppression de ton compte est annulée.'
}, { immediate: true })

async function dismiss(): Promise<void> {
  deletionCancelled.value = false
  await nextTick()
  document.getElementById('main')?.focus()
}
</script>

<template>
  <p
    class="sr-only"
    role="status"
  >
    {{ spoken }}
  </p>
  <div
    v-if="deletionCancelled"
    class="rounded-md border border-l-4 border-line border-l-success bg-surface-raised px-4 py-3 text-sm"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <p>La suppression de ton compte est annulée&nbsp;: tout est conservé.</p>
      <UiButton
        variant="ghost"
        @click="dismiss"
      >
        Compris
      </UiButton>
    </div>
  </div>
</template>
