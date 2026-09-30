<script setup lang="ts">
import { Search } from 'lucide-vue-next'
import type { SearchResult } from '~/features/search/sources'
import { useSearch } from '~/features/search/useSearch'
import { counted } from '~/services/text/plural'

/**
 * The global search (spec §15, ⌘K / Ctrl+K): pages, foods, recipes, exercises, programs and
 * sessions. A combobox over a list: the arrows move through the results, Entrée opens one,
 * Échap closes (the native <dialog> keeps the focus inside and gives it back).
 */
const open = defineModel<boolean>('open', { required: true })

const dialog = ref<HTMLDialogElement | null>(null)
const input = ref<HTMLInputElement | null>(null)
const query = ref('')
const active = ref(0)
const { results, groups, searchingFoods, refreshLists } = useSearch(query)
const ids = { title: useId(), list: useId() }
const optionId = (index: number) => `${ids.list}-option-${index}`

// A new query starts from its first result; results that arrive meanwhile (the foods, a list)
// keep the one the user moved to, wherever it now is.
let activeId: string | null = null
watch(query, () => {
  active.value = 0
  activeId = null
}, { flush: 'sync' })
watch(results, (now) => {
  const kept = activeId === null ? -1 : now.findIndex(result => result.id === activeId)
  active.value = kept >= 0 ? kept : Math.min(active.value, Math.max(0, now.length - 1))
})
watch([active, results], () => {
  activeId = results.value[active.value]?.id ?? null
})
const announcement = computed(() => {
  if (!query.value.trim()) return ''
  const count = results.value.length
  if (count) return `${counted(count, 'résultat')}${searchingFoods.value ? ', recherche des aliments…' : ''}`
  return searchingFoods.value ? 'Recherche…' : 'Aucun résultat'
})

async function sync(value: boolean): Promise<void> {
  const element = dialog.value
  if (!element) return
  if (value && !element.open) {
    query.value = ''
    // Written to elsewhere since the last opening, perhaps: read again, shown meanwhile.
    void refreshLists()
    element.showModal()
    await nextTick()
    input.value?.focus()
  }
  if (!value && element.open) element.close()
}
watch(open, sync, { flush: 'post' })
// Loaded on first use: it is mounted already open.
onMounted(() => sync(open.value))

async function go(result: SearchResult | undefined): Promise<void> {
  if (!result) return
  open.value = false
  await navigateTo(result.to)
}

function scrollToActive(): void {
  void nextTick(() => document.getElementById(optionId(active.value))?.scrollIntoView({ block: 'nearest' }))
}
function onKey(event: KeyboardEvent): void {
  const last = results.value.length - 1
  // The arrows wrap around, as in the other comboboxes of the platforms.
  const moves: Record<string, number> = { ArrowDown: active.value >= last ? 0 : active.value + 1, ArrowUp: active.value <= 0 ? last : active.value - 1, Home: 0, End: last }
  if (event.key in moves && last >= 0) {
    event.preventDefault()
    active.value = moves[event.key]!
    scrollToActive()
  }
  else if (event.key === 'Enter') {
    event.preventDefault()
    void go(results.value[active.value])
  }
}

function closeOnBackdrop(event: MouseEvent): void {
  if (event.target === dialog.value) open.value = false
}
</script>

<template>
  <dialog
    ref="dialog"
    class="mx-auto mt-[10dvh] w-[calc(100%-2rem)] max-w-xl rounded-lg border border-line bg-surface p-0 text-ink shadow-raised backdrop:bg-scrim"
    :aria-labelledby="ids.title"
    @close="open = false"
    @click="closeOnBackdrop"
  >
    <h2
      :id="ids.title"
      class="sr-only"
    >
      Rechercher
    </h2>
    <div class="flex items-center gap-2 border-b border-line px-4">
      <Search
        :size="20"
        class="shrink-0 text-muted"
        aria-hidden="true"
      />
      <input
        ref="input"
        v-model="query"
        type="search"
        class="h-tap min-w-0 flex-1 bg-transparent text-base focus:outline-none"
        placeholder="Aliment, exercice, page…"
        aria-label="Rechercher"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="Boolean(results.length)"
        :aria-controls="ids.list"
        :aria-activedescendant="results.length ? optionId(active) : undefined"
        autocomplete="off"
        enterkeyhint="go"
        @keydown="onKey"
      >
    </div>
    <p
      class="sr-only"
      role="status"
    >
      {{ announcement }}
    </p>
    <div
      :id="ids.list"
      role="listbox"
      aria-label="Résultats"
      class="max-h-[60dvh] overflow-y-auto p-2"
    >
      <div
        v-for="group in groups"
        :key="group.value"
        role="group"
        :aria-labelledby="`${ids.list}-${group.value}`"
        class="py-1"
      >
        <p
          :id="`${ids.list}-${group.value}`"
          class="px-2 py-1 text-xs font-semibold tracking-caps text-muted uppercase"
        >
          {{ group.label }}
        </p>
        <div
          v-for="{ result, index } in group.results"
          :id="optionId(index)"
          :key="result.id"
          role="option"
          :aria-selected="index === active"
          class="flex min-h-tap cursor-pointer items-center justify-between gap-3 rounded-sm px-2 aria-selected:bg-accent-soft aria-selected:text-accent-ink"
          @click="go(result)"
          @pointermove="active = index"
        >
          <span>{{ result.label }}</span>
          <span
            v-if="result.detail"
            class="text-sm text-muted"
          >{{ result.detail }}</span>
        </div>
      </div>
    </div>
    <!-- Outside the listbox: only options belong in it. -->
    <p
      v-if="!results.length && query.trim() && !searchingFoods"
      class="px-4 pb-4 text-sm text-muted"
    >
      Rien trouvé pour «&nbsp;{{ query.trim() }}&nbsp;».
    </p>
    <p class="hidden border-t border-line px-4 py-2 text-xs text-muted md:block">
      ↑ ↓ pour choisir · Entrée pour ouvrir · Échap pour fermer
    </p>
  </dialog>
</template>
