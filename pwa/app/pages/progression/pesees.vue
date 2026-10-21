<script setup lang="ts">
import { Scale } from 'lucide-vue-next'
import { formatKg, formatSignedKg } from '~/features/progression/format'
import { isWeightDerived, PROGRESSION_KEYS, type WeighIn } from '~/features/progression/progression'
import { formatFullDay, formatLongMonth, monthStart } from '~/services/calendar/days'

definePageMeta({ title: 'Pesées' })

const PAGE = 30
const api = useApi()
const { data: firstPage, error, refresh } = useAsyncData(PROGRESSION_KEYS.weighIns, () => api.get('/api/progression/weigh-ins', { query: { limit: PAGE } }))
const more = ref<WeighIn[]>([])
const weighIns = computed(() => [...(firstPage.value ?? []), ...more.value])
/**
 * By month, the latest first, each weigh-in with how far it moved from the one before it (the
 * last one loaded has none until older ones are read). `index` is its place in the whole list.
 */
const months = computed(() => {
  const groups: Array<{ month: string, title: string, rows: Array<{ weighIn: WeighIn, index: number, change: string | null }> }> = []
  weighIns.value.forEach((weighIn, index) => {
    const before = weighIns.value[index + 1]
    const row = { weighIn, index, change: before ? formatSignedKg(weighIn.weightKg - before.weightKg) : null }
    const month = monthStart(weighIn.day)
    const group = groups.at(-1)
    if (group?.month === month) group.rows.push(row)
    else groups.push({ month, title: formatLongMonth(month), rows: [row] })
  })
  return groups
})
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
        Pour corriger une pesée,
        <NuxtLink
          :to="{ path: '/progression', query: { ajouter: 'pesee' } }"
          class="font-semibold text-accent-ink"
        >enregistre de nouveau ce jour-là</NuxtLink>.
      </p>
      <section
        v-for="group in months"
        :key="group.month"
        class="flex flex-col gap-1"
      >
        <h2 class="text-lg font-semibold first-letter:uppercase">
          {{ group.title }}
        </h2>
        <ul
          class="m-0 flex flex-col divide-y divide-line p-0"
          role="list"
        >
          <li
            v-for="row in group.rows"
            :key="row.weighIn.day"
            class="flex items-center justify-between gap-3 py-1"
          >
            <span class="flex flex-col">
              <span>
                <span class="font-semibold tabular-nums">{{ formatKg(row.weighIn.weightKg) }}</span>
                <span
                  v-if="row.change"
                  class="text-sm text-muted tabular-nums"
                ><span aria-hidden="true"> · </span>{{ row.change }}<span class="sr-only"> depuis la pesée d’avant</span></span>
              </span>
              <span class="text-sm text-muted">{{ formatFullDay(row.weighIn.day) }}</span>
            </span>
            <UiConfirmButton
              label="Retirer"
              :accessible-label="`Retirer la pesée du ${formatFullDay(row.weighIn.day)}`"
              confirm-label="Confirmer"
              variant="ghost"
              data-remove
              @confirm="remove(row.weighIn, row.index)"
            />
          </li>
        </ul>
      </section>
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
