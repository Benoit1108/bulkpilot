<script setup lang="ts">
import { encode } from 'uqr'

const props = defineProps<{
  secret: string
  otpauthUri: string
  pending: boolean
  error?: string
}>()
const emit = defineEmits<{ submit: [code: string], cancel: [] }>()

const code = ref('')
const copied = ref(false)

// Drawn locally: the seed never leaves the device through a third-party image service.
const qr = computed(() => {
  const { data, size } = encode(props.otpauthUri, { border: 2 })
  const path = data.flatMap((row, y) => row.flatMap((dark, x) => (dark ? [`M${x} ${y}h1v1h-1z`] : []))).join('')
  return { size, path }
})
const groups = computed(() => props.secret.match(/.{1,4}/g) ?? [])

async function copySecret(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.secret)
    copied.value = true
  }
  catch {
    copied.value = false
  }
}
</script>

<template>
  <form
    class="form"
    novalidate
    @submit.prevent="emit('submit', code)"
  >
    <!-- On a phone, the authenticator app is on the same device: open it directly. -->
    <a
      class="enrol__open"
      :href="otpauthUri"
    >Ouvrir dans mon application d’authentification</a>

    <p>Ou scanne ce QR code depuis ton application&nbsp;:</p>
    <svg
      class="enrol__qr"
      :viewBox="`0 0 ${qr.size} ${qr.size}`"
      role="img"
      aria-label="QR code de configuration de l’application d’authentification"
      shape-rendering="crispEdges"
    >
      <rect
        :width="qr.size"
        :height="qr.size"
        fill="#fff"
      />
      <path
        :d="qr.path"
        fill="#000"
      />
    </svg>

    <div class="enrol__key">
      <p class="enrol__hint">
        Ou saisis cette clé à la main&nbsp;:
      </p>
      <code data-testid="totp-secret"><span
        v-for="(group, index) in groups"
        :key="index"
        class="enrol__group"
      >{{ group }}</span></code>
      <UiButton
        variant="ghost"
        @click="copySecret"
      >
        {{ copied ? 'Clé copiée' : 'Copier la clé' }}
      </UiButton>
      <span
        class="visually-hidden"
        role="status"
      >{{ copied ? 'Clé copiée' : '' }}</span>
    </div>

    <UiInput
      v-model="code"
      label="Code à 6 chiffres affiché par l’application"
      inputmode="numeric"
      autocomplete="one-time-code"
      :maxlength="7"
      pattern="[0-9 ]*"
      required
      :error="error"
    />
    <div class="form__actions">
      <UiButton
        type="submit"
        :loading="pending"
      >
        Activer
      </UiButton>
      <UiButton
        variant="ghost"
        @click="emit('cancel')"
      >
        Annuler
      </UiButton>
    </div>
  </form>
</template>

<style scoped>
.enrol__open {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: var(--tap-target);
  padding: 0 var(--space-4);
  background: var(--color-accent-fill);
  border-radius: var(--radius-md);
  color: var(--color-accent-contrast);
  font-weight: 600;
  text-align: center;
  text-decoration: none;
}

/* Scanning is for another device: on touch screens the direct link comes first, the QR stays. */
@media (hover: hover) and (pointer: fine) {
  .enrol__open {
    display: none;
  }
}

.enrol__qr {
  display: block;
  width: min(14rem, 100%);
  height: auto;
  border-radius: var(--radius-sm);
}

.enrol__key {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
}

.enrol__hint {
  flex-basis: 100%;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.enrol__key code {
  display: flex;
  flex-wrap: wrap;
  gap: 0 0.5ch;
  font-family: ui-monospace, 'SF Mono', Menlo, monospace;
}

.enrol__group {
  white-space: nowrap;
}
</style>
