<script setup lang="ts">
import { Laptop, Smartphone } from 'lucide-vue-next'
import type { DeviceSession } from '~/features/identity/account'

const api = useApi()
const { data: sessions, refresh } = useAsyncData('identity-sessions', () => api.get('/api/me/sessions'))
const { pending, message, run } = useSubmission()

const lastSeen = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' })

function isMobile(session: DeviceSession): boolean {
  return /Android|iOS|iPadOS/.test(session.deviceLabel)
}

async function revoke(session: DeviceSession): Promise<void> {
  if (await run(() => api.delete('/api/me/sessions/{id}', { path: { id: session.id } }))) {
    await refresh()
  }
}
</script>

<template>
  <div>
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <ul class="m-0 flex list-none flex-col divide-y divide-line p-0">
      <li
        v-for="session in sessions ?? []"
        :key="session.id"
        class="flex items-center gap-3 py-3"
      >
        <component
          :is="isMobile(session) ? Smartphone : Laptop"
          :size="22"
          aria-hidden="true"
          class="flex-none text-muted"
        />
        <div class="min-w-0 flex-1">
          <p class="font-semibold">
            {{ session.deviceLabel }}
            <span
              v-if="session.current"
              class="ml-2 rounded-full bg-accent-soft px-2 py-0.5 text-xs font-bold text-accent-ink"
            >Cet appareil</span>
          </p>
          <p class="text-sm text-muted">
            Actif le {{ lastSeen.format(new Date(session.lastSeenAt)) }}
          </p>
        </div>
        <UiButton
          v-if="!session.current"
          variant="ghost"
          :disabled="pending"
          :aria-label="`Déconnecter ${session.deviceLabel}`"
          @click="revoke(session)"
        >
          Déconnecter
        </UiButton>
      </li>
    </ul>
  </div>
</template>
