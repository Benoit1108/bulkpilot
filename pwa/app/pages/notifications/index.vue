<script setup lang="ts">
import { Bell } from 'lucide-vue-next'
import { useNotificationsBadge } from '~/features/notifications/badge'
import { CATEGORIES, NOTIFICATION_KEYS, type AppNotification } from '~/features/notifications/notifications'
import { formatMoment } from '~/services/calendar/days'

definePageMeta({ title: 'Notifications' })

const api = useApi()
const badge = useNotificationsBadge()
const paged = usePagedList<AppNotification>(
  NOTIFICATION_KEYS.list,
  cursor => api.get('/api/notifications', { query: { limit: 20, ...cursor } }),
  { size: 20, noun: ['notification'] },
)
const { firstPage, items: notifications, status, error, refresh, hasMore, loading, notice, list, keep, loadMore } = paged
// Unread beyond the pages shown count too: the bell knows them all.
const unread = computed(() => badge.count.value > 0 || notifications.value.some(notification => !notification.readAt))
// A check that created some while the page is open: the list is read again.
watch(badge.count, (now, before) => {
  if (now > before) void refresh()
})

const marking = useSubmission()
const emptyText = `Seules les choses importantes arrivent ici\u00A0: ${CATEGORIES.map(category => category.label.toLowerCase()).join(', ')}. Chacune se règle dans les réglages des notifications.`

const actionable = (notification: AppNotification) => !notification.readAt || notification.link !== null

/** Opening one reads it, then goes where it points to; without a link, it stays and says so. */
async function open(notification: AppNotification): Promise<void> {
  const done = await marking.run(async () => {
    if (!notification.readAt) {
      paged.replace(await api.post('/api/notifications/{id}/read', undefined, { path: { id: notification.id } }))
      await badge.refresh()
    }
  })
  if (!done) return
  if (notification.link) {
    await navigateTo(notification.link)
    return
  }
  // The button became plain text: the focus stays on its item.
  notice.value = 'Notification marquée comme lue.'
  await nextTick()
  paged.element(notification.id)?.focus()
}

async function readAll(): Promise<void> {
  const done = await marking.run(async () => {
    await api.post('/api/notifications/read-all')
    const now = new Date().toISOString()
    paged.updateAll(notification => ({ ...notification, readAt: notification.readAt ?? now }))
    await badge.refresh()
  })
  if (!done) return
  notice.value = 'Toutes les notifications sont lues.'
  // The button is gone: the focus goes to the list.
  await nextTick()
  list.value?.focus()
}
</script>

<template>
  <UiPage>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <NuxtLink
        to="/notifications/reglages"
        class="min-h-tap content-center font-semibold text-accent-ink"
      >
        Réglages des notifications
      </NuxtLink>
      <UiButton
        v-if="unread"
        variant="secondary"
        :loading="marking.pending.value"
        @click="readAll"
      >
        Tout marquer comme lu
      </UiButton>
    </div>
    <UiAlert
      v-if="marking.message.value"
      tone="error"
    >
      {{ marking.message.value }}
    </UiAlert>
    <UiLoadError
      v-if="error && !firstPage"
      @retry="refresh()"
    />
    <UiCard v-else-if="notifications.length">
      <UiAlert
        v-if="loading.message.value"
        tone="error"
      >
        {{ loading.message.value }}
      </UiAlert>
      <ul
        ref="list"
        class="m-0 flex list-none flex-col p-0"
        role="list"
        aria-label="Notifications, la plus récente d’abord"
        tabindex="-1"
      >
        <li
          v-for="notification in notifications"
          :key="notification.id"
          :ref="element => keep(notification.id, element)"
          :data-unread="notification.readAt === null"
          class="border-line not-first:border-t"
          tabindex="-1"
        >
          <!-- A button while there is something to do (read it, go to it); plain text once read, without a link. -->
          <component
            :is="actionable(notification) ? 'button' : 'div'"
            :type="actionable(notification) ? 'button' : undefined"
            class="flex w-full items-start gap-3 bg-transparent px-0 py-3 text-left text-ink"
            :class="{ 'cursor-pointer': actionable(notification) }"
            @click="actionable(notification) && open(notification)"
          >
            <!-- Unread: a dot and the words, never the colour alone. -->
            <span
              class="mt-2 size-2.5 shrink-0 rounded-full"
              :class="notification.readAt ? 'bg-transparent' : 'bg-accent-fill'"
              aria-hidden="true"
            />
            <span class="flex min-w-0 flex-col gap-1">
              <span :class="notification.readAt ? 'font-semibold' : 'font-bold'">
                <span
                  v-if="!notification.readAt"
                  class="sr-only"
                >Non lue&nbsp;: </span>{{ notification.title }}
              </span>
              <span class="text-sm">{{ notification.body }}</span>
              <span class="text-sm text-muted">
                {{ formatMoment(notification.createdAt) }}<template v-if="!notification.readAt"> · <span aria-hidden="true">Non lue</span></template>
              </span>
            </span>
          </component>
        </li>
      </ul>
      <UiButton
        v-if="hasMore"
        variant="secondary"
        class="self-start"
        :loading="loading.pending.value"
        @click="loadMore"
      >
        Voir plus
      </UiButton>
    </UiCard>
    <UiCard v-else-if="firstPage">
      <UiEmptyState
        :icon="Bell"
        title="Aucune notification"
        :description="emptyText"
      />
    </UiCard>
    <p
      v-else-if="status === 'pending'"
      class="text-muted"
      role="status"
    >
      Chargement…
    </p>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </UiPage>
</template>
