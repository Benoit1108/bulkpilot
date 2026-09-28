<script setup lang="ts">
import { X } from 'lucide-vue-next'

/**
 * Modal panel on a native <dialog>: focus is trapped and restored by the browser, Escape closes it.
 * A bottom sheet on phones, a side panel from 48rem.
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
  <dialog
    ref="dialog"
    class="ui-drawer"
    :aria-labelledby="titleId"
    @close="open = false"
    @pointerdown="press"
    @click="closeOnBackdrop"
  >
    <div class="ui-drawer__panel">
      <header class="ui-drawer__header">
        <h2
          :id="titleId"
          class="ui-drawer__title"
        >
          {{ title }}
        </h2>
        <button
          type="button"
          class="ui-drawer__close"
          aria-label="Fermer"
          @click="open = false"
        >
          <X
            :size="20"
            aria-hidden="true"
          />
        </button>
      </header>
      <div class="ui-drawer__body">
        <slot v-if="open" />
      </div>
    </div>
  </dialog>
</template>

<style scoped>
.ui-drawer {
  width: 100%;
  max-width: 100%;
  max-height: 90dvh;
  margin: auto 0 0;
  padding: 0;
  background: var(--color-surface);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  box-shadow: var(--shadow-raised);
}

.ui-drawer::backdrop {
  background: rgb(0 0 0 / 0.5);
}

.ui-drawer__panel {
  display: flex;
  flex-direction: column;
  max-height: 90dvh;
}

.ui-drawer__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--color-border);
}

.ui-drawer__title {
  font-size: var(--text-lg);
  font-weight: 700;
}

.ui-drawer__close {
  display: grid;
  place-items: center;
  width: var(--tap-target);
  height: var(--tap-target);
  padding: 0;
  background: transparent;
  border: 0;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
  cursor: pointer;
}

.ui-drawer__body {
  overflow-y: auto;
  padding: var(--space-4);
  padding-bottom: max(var(--space-4), env(safe-area-inset-bottom));
}

@media (min-width: 48rem) {
  .ui-drawer {
    width: 28rem;
    height: 100dvh;
    max-height: 100dvh;
    margin: 0 0 0 auto;
    border-radius: var(--radius-lg) 0 0 var(--radius-lg);
  }

  .ui-drawer__panel {
    height: 100%;
    max-height: 100dvh;
  }
}
</style>
