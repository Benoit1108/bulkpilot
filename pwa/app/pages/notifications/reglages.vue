<script setup lang="ts">
import NotificationSettingsForm from '~/features/notifications/components/NotificationSettingsForm.vue'
import { NOTIFICATION_KEYS, type NotificationSettings } from '~/features/notifications/notifications'

definePageMeta({ title: 'Réglages des notifications' })

const api = useApi()
const { data: settings, error, refresh } = useAsyncData(NOTIFICATION_KEYS.settings, () => api.get('/api/notifications/settings'))

function onSaved(saved: NotificationSettings): void {
  settings.value = saved
}
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/notifications"
      label="Notifications"
    />
    <UiLoadError
      v-if="error && !settings"
      @retry="refresh()"
    />
    <UiCard v-else-if="settings">
      <NotificationSettingsForm
        :settings="settings"
        @saved="onSaved"
      />
    </UiCard>
    <p
      v-else
      class="text-muted"
      role="status"
    >
      Chargement…
    </p>
  </div>
</template>
