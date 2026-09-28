<script setup lang="ts">
import type { ThemePreference } from '~/features/shell/theme'
import { useServiceHealth } from '~/features/system/health'

definePageMeta({ title: 'Réglages' })

const { preference, setPreference } = useTheme()
const { serviceStatus, refresh } = useServiceHealth()
const appName = useRuntimeConfig().public.appName

const themeOptions: ReadonlyArray<{ value: ThemePreference, label: string }> = [
  { value: 'dark', label: 'Sombre' },
  { value: 'light', label: 'Clair' },
  { value: 'system', label: 'Système' },
]

const statusLabels = {
  checking: 'Vérification…',
  ok: 'Connecté',
  degraded: 'Service dégradé',
  unreachable: 'Serveur injoignable',
} as const
</script>

<template>
  <div class="page">
    <UiCard title="Apparence">
      <fieldset class="choice">
        <legend class="choice__legend">
          Thème
        </legend>
        <label
          v-for="option in themeOptions"
          :key="option.value"
          class="choice__option"
        >
          <input
            type="radio"
            name="theme"
            :value="option.value"
            :checked="preference === option.value"
            @change="setPreference(option.value)"
          >
          <span>{{ option.label }}</span>
        </label>
      </fieldset>
    </UiCard>

    <UiCard title="À propos">
      <dl class="about">
        <dt>Application</dt>
        <dd>{{ appName }}</dd>
        <dt>Serveur</dt>
        <dd>
          <span
            class="status"
            :class="`status--${serviceStatus}`"
            data-testid="service-status"
          >{{ statusLabels[serviceStatus] }}</span>
        </dd>
      </dl>
      <UiButton
        v-if="serviceStatus === 'unreachable' || serviceStatus === 'degraded'"
        variant="secondary"
        @click="refresh()"
      >
        Réessayer
      </UiButton>
    </UiCard>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-width: var(--reading-max-width);
}

.choice {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  border: 0;
}

.choice__legend {
  margin-bottom: var(--space-2);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.choice__option {
  position: relative;
  display: grid;
  place-items: center;
  min-height: var(--tap-target);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface-raised);
  font-weight: 600;
  cursor: pointer;
}

.choice__option input {
  position: absolute;
  opacity: 0;
  inset: 0;
  margin: 0;
  cursor: pointer;
}

.choice__option:has(input:checked) {
  border-color: var(--color-accent);
  background: var(--color-accent-soft);
  color: var(--color-accent-text);
}

.choice__option:has(input:focus-visible) {
  outline: 2px solid var(--color-accent);
  outline-offset: 2px;
}

.about {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: var(--space-2) var(--space-4);
  margin: 0;
}

.about dt {
  color: var(--color-text-muted);
}

.about dd {
  margin: 0;
}

.status {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
}

.status::before {
  content: '';
  width: 0.625rem;
  height: 0.625rem;
  border-radius: var(--radius-full);
  background: var(--color-text-muted);
}

.status--ok::before {
  background: var(--color-success);
}

.status--degraded::before,
.status--unreachable::before {
  background: var(--color-danger);
}
</style>
