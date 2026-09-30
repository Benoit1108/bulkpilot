<script setup lang="ts">
import { Bell } from 'lucide-vue-next'
import { HEADER_BUTTON } from '~/components/ui/classes'
import { useNotificationsBadge } from '~/features/notifications/badge'
import { plural } from '~/services/text/plural'

/** The way to the notifications, with how many are unread (said in words, not by the dot alone). */
const { count, refresh } = useNotificationsBadge()
const route = useRoute()
const label = computed(() => (count.value ? `Notifications, ${count.value} ${plural(count.value, 'non lue', 'non lues')}` : 'Notifications'))
const badge = computed(() => (count.value > 9 ? '9+' : String(count.value)))

// Read once here; the notifications plugin refreshes it when the app comes back to the foreground.
onMounted(() => void refresh())
</script>

<template>
  <NuxtLink
    to="/notifications"
    :class="HEADER_BUTTON"
    :aria-current="route.path.startsWith('/notifications') ? 'page' : undefined"
    :aria-label="label"
    :title="label"
  >
    <Bell
      :size="20"
      aria-hidden="true"
    />
    <span
      v-if="count"
      class="absolute -top-1 -right-1 grid min-w-5 place-items-center rounded-full bg-accent-fill px-1 text-xs font-bold text-accent-contrast"
      aria-hidden="true"
      data-testid="unread-badge"
    >{{ badge }}</span>
  </NuxtLink>
</template>
