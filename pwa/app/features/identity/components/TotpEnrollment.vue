<script setup lang="ts">
import { encode } from 'uqr'
import { buttonClass } from '~/components/ui/classes'

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
    class="flex flex-col gap-4"
    novalidate
    @submit.prevent="emit('submit', code)"
  >
    <!--
      On a phone, the authenticator app is on the same device: open it directly. Scanning is for
      another device: with a mouse (hover + fine pointer) only the QR code is offered.
    -->
    <a
      class="text-center [@media(hover:hover)_and_(pointer:fine)]:hidden"
      :class="buttonClass('primary')"
      :href="otpauthUri"
    >Ouvrir dans mon application d’authentification</a>

    <p>Ou scanne ce QR code depuis ton application&nbsp;:</p>
    <svg
      class="block h-auto w-[min(14rem,100%)] rounded-sm"
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

    <div class="flex flex-wrap items-center gap-2">
      <p class="basis-full text-sm text-muted">
        Ou saisis cette clé à la main&nbsp;:
      </p>
      <code
        class="flex flex-wrap gap-x-[0.5ch] font-mono"
        data-testid="totp-secret"
      ><span
        v-for="(group, index) in groups"
        :key="index"
        class="whitespace-nowrap"
      >{{ group }}</span></code>
      <UiButton
        variant="ghost"
        @click="copySecret"
      >
        {{ copied ? 'Clé copiée' : 'Copier la clé' }}
      </UiButton>
      <span
        class="sr-only"
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
    <div class="flex flex-wrap gap-2">
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
