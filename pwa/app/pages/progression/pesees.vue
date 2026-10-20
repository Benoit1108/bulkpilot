<script setup lang="ts">
import { Scale } from 'lucide-vue-next'
import { formatKg } from '~/features/progression/format'
import { isWeightDerived, PROGRESSION_KEYS, type WeighIn } from '~/features/progression/progression'
import { formatFullDay } from '~/services/calendar/days'

definePageMeta({ title: 'Pesées' })

const PAGE = 30
const api = useApi()
const { data: firstPage, error, refresh } = useAsyncData(PROGRESSION_KEYS.weighIns, () => api.get('/api/progression/weigh-ins', { query: { limit: PAGE } }))
const more = ref<WeighIn[]>([])
const weighIns = computed(() => [...(firstPage.value ?? []), ...more.value])
const hasMore = ref(true)
watch(firstPage, (page) => {
  more.value = []
  hasMore.value = (page?.length ?? 0) === PAGE
}, { immediate: true })

const loading = useSubmission()
async function loadMore(): Promise<void> {
  const before = weighIns.value.at(-1)?.day
  await loading.run(async () => {
    const page = await api.get('/api/progression/weigh-ins', { query: { limit: PAGE, before } })
    more.value.push(...page)
    hasMore.value = page.length === PAGE
  })
}

const removal = useSubmission()
const notice = ref<string | null>(null)
const page = ref<HTMLElement | null>(null)
async function remove(weighIn: WeighIn, index: number): Promise<void> {
  if (await removal.run(() => api.delete('/api/progression/weigh-ins/{day}', { path: { day: weighIn.day } }))) {
    notice.value = `Pesée du ${formatFullDay(weighIn.day)} retirée.`
    // The list is read again in place; the charts and goal that used the weigh-in are dropped.
    clearNuxtData(key => isWeightDerived(key) && key !== PROGRESSION_KEYS.weighIns)
    await refresh()
    await nextTick()
    // Focus moves to the weigh-in that took its place (or the one before), else to the page.
    const buttons = page.value?.querySelectorAll<HTMLElement>('[data-remove]') ?? []
    ;(buttons[Math.min(index, buttons.length - 1)] ?? page.value)?.focus()
  }
}
</script>

<template>
  <div
    ref="page"
    class="flex max-w-reading flex-col gap-4 focus:outline-none"
    tabindex="-1"
  >
    <!-- UiPage's reading layout, on an element of its own: the page needs a ref on its root. -->
    <ShellBackLink
      to="/progression"
      label="Progression"
    />
    <UiLoadError
      v-if="error && !firstPage"
      @retry="refresh()"
    />
    <UiCard v-else-if="weighIns.length">
      <UiAlert
        v-if="removal.message.value"
        tone="error"
      >
        {{ removal.message.value }}
      </UiAlert>
      <p class="text-sm text-muted">
        Pour corriger une pesée, enregistre de nouveau ce jour-là depuis la page Progression.
      </p>
      <ul class="m-0 flex list-none flex-col p-0">
        <li
          v-for="(weighIn, i) in weighIns"
          :key="weighIn.day"
          class="flex items-center justify-between gap-3 border-t border-line py-1"
        >
          <span>
            <span class="font-semibold tabular-nums">{{ formatKg(weighIn.weightKg) }}</span>
            <span class="text-muted"> · {{ formatFullDay(weighIn.day) }}</span>
          </span>
          <UiConfirmButton
            label="Retirer"
            :accessible-label="`Retirer la pesée du ${formatFullDay(weighIn.day)}`"
            confirm-label="Confirmer"
            variant="ghost"
            data-remove
            @confirm="remove(weighIn, i)"
          />
        </li>
      </ul>
      <UiButton
        v-if="hasMore"
        variant="secondary"
        class="self-start"
        :loading="loading.pending.value"
        @click="loadMore"
      >
        Pesées plus anciennes
      </UiButton>
    </UiCard>
    <UiCard v-else-if="firstPage">
      <UiEmptyState
        :icon="Scale"
        title="Aucune pesée"
        description="Tes pesées apparaissent ici, la plus récente d’abord."
      />
    </UiCard>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
</template>
