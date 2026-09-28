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
  <div class="devices">
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <ul class="devices__list">
      <li
        v-for="session in sessions ?? []"
        :key="session.id"
        class="devices__item"
      >
        <component
          :is="isMobile(session) ? Smartphone : Laptop"
          :size="22"
          aria-hidden="true"
          class="devices__icon"
        />
        <div class="devices__text">
          <p class="devices__label">
            {{ session.deviceLabel }}
            <span
              v-if="session.current"
              class="devices__current"
            >Cet appareil</span>
          </p>
          <p class="devices__meta">
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

<style scoped>
.devices__list {
  display: flex;
  flex-direction: column;
  margin: 0;
  padding: 0;
  list-style: none;
}

.devices__item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) 0;
  border-top: 1px solid var(--color-border);
}

.devices__item:first-child {
  border-top: 0;
}

.devices__icon {
  flex: none;
  color: var(--color-text-muted);
}

.devices__text {
  flex: 1;
  min-width: 0;
}

.devices__label {
  font-weight: 600;
}

.devices__current {
  margin-left: var(--space-2);
  padding: 2px var(--space-2);
  border-radius: var(--radius-full);
  background: var(--color-accent-soft);
  color: var(--color-accent-text);
  font-size: var(--text-xs);
  font-weight: 700;
}

.devices__meta {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
