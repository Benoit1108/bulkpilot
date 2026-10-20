<script setup lang="ts">
import { X } from 'lucide-vue-next'

/**
 * Modal panel on a native <dialog>: focus is trapped and restored by the browser, Escape closes it.
 */
const open = defineModel<boolean>('open', { required: true })

defineProps<{ title: string }>()

const dialog = ref<HTMLDialogElement | null>(null)
const titleId = useId()

watch(open, (value) => {
  const element = dialog.value
  if (!element) return
  if (value && !element.open) element.showModal()
  if (!value && element.open) element.close()
}, { flush: 'post' })

onMounted(() => {
  if (open.value) dialog.value?.showModal()
})

// A press that starts in a field and ends on the backdrop (selecting text) must not close it.
let pressedOnBackdrop = false
function press(event: PointerEvent): void {
  pressedOnBackdrop = event.target === dialog.value
}
function closeOnBackdrop(event: MouseEvent): void {
  if (pressedOnBackdrop && event.target === dialog.value) open.value = false
  pressedOnBackdrop = false
}
</script>

<template>
  <!-- A bottom sheet on phones, a side panel from md. -->
  <dialog
    ref="dialog"
    class="mx-0 mt-auto mb-0 max-h-[90dvh] w-full max-w-full rounded-t-xl border border-line bg-surface p-0 text-ink shadow-raised backdrop:bg-scrim md:mt-0 md:ml-auto md:h-dvh md:max-h-dvh md:w-md md:rounded-l-xl md:rounded-tr-none"
    :aria-labelledby="titleId"
    @close="open = false"
    @pointerdown="press"
    @click="closeOnBackdrop"
  >
    <div class="flex max-h-[90dvh] flex-col md:h-full md:max-h-dvh">
      <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
        <h2
          :id="titleId"
          class="text-lg font-bold"
        >
          {{ title }}
        </h2>
        <button
          type="button"
          class="grid size-tap cursor-pointer place-items-center rounded-sm bg-transparent p-0 text-muted"
          aria-label="Fermer"
          @click="open = false"
        >
          <X
            :size="20"
            aria-hidden="true"
          />
        </button>
      </header>
      <div class="overflow-y-auto p-4 pb-safe-or-4">
        <slot v-if="open" />
      </div>
    </div>
  </dialog>
</template>
