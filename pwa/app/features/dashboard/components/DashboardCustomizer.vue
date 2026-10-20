<script setup lang="ts">
import { ArrowDown, ArrowUp } from 'lucide-vue-next'
import { isKnownWidget, moveWidget, WIDGET_LABELS, type Widget } from '../dashboard'

/**
 * Arranges the dashboard (spec §82): each widget shown or hidden, moved up or down with buttons
 * (a keyboard and a screen reader can do it, no dragging needed). Saved for the account.
 */
const props = defineProps<{ widgets: Widget[], save: (widgets: Widget[]) => Promise<void> }>()
const emit = defineEmits<{ done: [saved: boolean] }>()

// Widgets a newer server knows and this app does not come last, untouched: they are sent back as they were.
const draft = ref<Widget[]>(props.widgets.filter(isKnownWidget).map(widget => ({ ...widget })))
const unknown = props.widgets.filter(widget => !isKnownWidget(widget))
const body = () => [...draft.value, ...unknown].map(({ key, visible }) => ({ key, visible }))
const { markSaved } = useUnsavedChanges(body)
const { pending, message, run } = useSubmission()
const moved = ref('')

// Entering the mode: the focus goes to its title (the button that opened it is gone).
const heading = ref<HTMLElement | null>(null)
onMounted(() => heading.value?.focus())

const root = ref<HTMLElement | null>(null)
async function move(index: number, by: -1 | 1): Promise<void> {
  const widget = draft.value[index]!
  draft.value = moveWidget(draft.value, index, by)
  const position = index + by + 1
  moved.value = `${WIDGET_LABELS[widget.key]} en position ${position} sur ${draft.value.length}.`
  // The pressed button moved with its widget; at either end it is disabled, so the other one takes the focus.
  await nextTick()
  const buttons = root.value?.querySelectorAll<HTMLButtonElement>(`[data-widget="${widget.key}"] button`)
  const same = buttons?.[by === -1 ? 0 : 1]
  ;(same && !same.disabled ? same : buttons?.[by === -1 ? 1 : 0])?.focus()
}

async function submit(): Promise<void> {
  const sent = body()
  if (await run(() => props.save(sent))) {
    markSaved(sent)
    emit('done', true)
  }
}

function cancel(): void {
  // Leaving the mode is not leaving the page: what was not saved is simply dropped.
  markSaved(body())
  emit('done', false)
}
</script>

<template>
  <UiCard as="div">
    <h2
      ref="heading"
      class="text-lg font-semibold tracking-snug"
      tabindex="-1"
    >
      Personnaliser le tableau de bord
    </h2>
    <form
      ref="root"
      class="flex flex-col gap-3"
      novalidate
      @submit.prevent="submit"
    >
      <p class="text-sm text-muted">
        Coche les widgets à afficher et range-les dans l’ordre voulu&nbsp;: c’est gardé sur tous tes appareils.
      </p>
      <p
        class="sr-only"
        role="status"
      >
        {{ moved }}
      </p>
      <UiAlert
        v-if="message"
        tone="error"
      >
        {{ message }}
      </UiAlert>
      <ol
        class="m-0 flex list-none flex-col p-0"
        role="list"
      >
        <li
          v-for="(widget, index) in draft"
          :key="widget.key"
          :data-widget="widget.key"
          class="flex items-center justify-between gap-2 border-t border-line first:border-t-0"
        >
          <UiCheckbox
            v-model="widget.visible"
            :label="WIDGET_LABELS[widget.key]"
          />
          <span class="flex gap-1">
            <UiButton
              variant="ghost"
              :disabled="index === 0"
              :aria-label="`Monter le widget ${WIDGET_LABELS[widget.key]}`"
              @click="move(index, -1)"
            >
              <ArrowUp
                :size="18"
                aria-hidden="true"
              />
            </UiButton>
            <UiButton
              variant="ghost"
              :disabled="index === draft.length - 1"
              :aria-label="`Descendre le widget ${WIDGET_LABELS[widget.key]}`"
              @click="move(index, 1)"
            >
              <ArrowDown
                :size="18"
                aria-hidden="true"
              />
            </UiButton>
          </span>
        </li>
      </ol>
      <div class="flex flex-wrap gap-2">
        <UiButton
          type="submit"
          :loading="pending"
        >
          Enregistrer
        </UiButton>
        <UiButton
          variant="secondary"
          @click="cancel"
        >
          Annuler
        </UiButton>
      </div>
    </form>
  </UiCard>
</template>
