<script setup lang="ts">
import { Flashlight, FlashlightOff } from 'lucide-vue-next'
import { createBarcodeReader, looksLikeBarcode, type BarcodeReader } from '~/features/nutrition/scanner'

/**
 * Camera preview that reports the first barcode it reads; the code can always be typed instead
 * (no camera, access refused, damaged label). The camera is off whenever the page is hidden.
 */
const emit = defineEmits<{ code: [code: string] }>()

type CameraState = 'starting' | 'scanning' | 'denied' | 'unavailable' | 'interrupted' | 'no-reader'
const state = ref<CameraState>('starting')
const video = ref<HTMLVideoElement | null>(null)
const typed = ref('')
const typedError = ref<string | undefined>(undefined)
const typedField = ref<{ focus: () => void } | null>(null)
/** Nothing read for a while: tips to help the camera. */
const slow = ref(false)
const torch = ref<{ on: boolean } | null>(null)

let stream: MediaStream | null = null
let reader: BarcodeReader | null = null
let timer: ReturnType<typeof setTimeout> | undefined
let slowTimer: ReturnType<typeof setTimeout> | undefined
/** Bumped by every stop: a loop or a start from before it gives up. */
let session = 0
let done = false

const SCAN_EVERY_MS = 250
const SLOW_AFTER_MS = 8000

async function start(): Promise<void> {
  const current = ++session
  state.value = 'starting'
  slow.value = false
  if (!navigator.mediaDevices?.getUserMedia) {
    state.value = 'unavailable'
    return
  }
  try {
    const opened = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }, audio: false })
    if (current !== session || !video.value) {
      // Stopped (or restarted) meanwhile: this stream is nobody's.
      opened.getTracks().forEach(track => track.stop())
      return
    }
    stream = opened
    const [track] = stream.getVideoTracks?.() ?? []
    track?.addEventListener('ended', interrupted)
    torch.value = (track?.getCapabilities?.() as { torch?: boolean } | undefined)?.torch ? { on: false } : null
    video.value.srcObject = stream
    await video.value.play()
  }
  catch (error) {
    if (current !== session) return
    release()
    state.value = error instanceof DOMException && error.name === 'NotAllowedError' ? 'denied' : 'unavailable'
    return
  }
  try {
    reader ??= await createBarcodeReader()
  }
  catch {
    if (current !== session) return
    release()
    state.value = 'no-reader'
    return
  }
  if (current !== session) return
  state.value = 'scanning'
  slowTimer = setTimeout(() => (slow.value = true), SLOW_AFTER_MS)
  void scan(current, reader)
}

async function scan(current: number, from: BarcodeReader): Promise<void> {
  if (current !== session || !video.value) return
  const code = await from.read(video.value).catch(() => null)
  // The camera may have been turned off while the frame was decoded.
  if (current !== session) return
  if (code && looksLikeBarcode(code)) {
    navigator.vibrate?.(50)
    finish(code)
    return
  }
  timer = setTimeout(() => void scan(current, from), SCAN_EVERY_MS)
}

/** Turns the camera off; start() turns it on again. */
function release(): void {
  session++
  clearTimeout(timer)
  clearTimeout(slowTimer)
  stream?.getTracks().forEach(track => track.stop())
  stream = null
  torch.value = null
}

function interrupted(): void {
  release()
  state.value = 'interrupted'
}

function finish(code: string): void {
  done = true
  release()
  emit('code', code)
}

async function toggleTorch(): Promise<void> {
  const [track] = stream?.getVideoTracks?.() ?? []
  if (!track || !torch.value) return
  const on = !torch.value.on
  try {
    await track.applyConstraints({ advanced: [{ torch: on } as MediaTrackConstraintSet] })
    torch.value = { on }
  }
  catch {
    torch.value = null
  }
}

/** A hidden page (app switch, locked phone) must not keep the camera on. */
function onVisibility(): void {
  if (done) return
  if (document.visibilityState === 'hidden') release()
  else if (state.value === 'starting' || state.value === 'scanning') void start()
}

onMounted(() => {
  document.addEventListener('visibilitychange', onVisibility)
  void start()
})

onBeforeUnmount(() => {
  done = true
  document.removeEventListener('visibilitychange', onVisibility)
  release()
})

function submitTyped(): void {
  const code = typed.value.replace(/[\s-]/g, '')
  if (!looksLikeBarcode(code)) {
    typedError.value = 'Un code-barres a 8, 12, 13 ou 14 chiffres.'
    typedField.value?.focus()
    return
  }
  typedError.value = undefined
  finish(code)
}
</script>

<template>
  <div
    class="flex flex-col gap-3"
    data-testid="scanner"
  >
    <div
      v-if="state === 'starting' || state === 'scanning'"
      class="relative aspect-4/3 overflow-hidden rounded-md bg-camera"
    >
      <!-- Decorative for screen readers: the typed code below is the accessible way in. -->
      <video
        ref="video"
        class="size-full object-cover"
        muted
        playsinline
        aria-hidden="true"
      />
      <span
        class="absolute inset-x-[8%] inset-y-[30%] rounded-sm border-2 border-accent-contrast shadow-mask"
        aria-hidden="true"
      />
      <UiButton
        v-if="torch"
        class="absolute right-2 bottom-2"
        variant="secondary"
        :aria-pressed="torch.on"
        @click="toggleTorch"
      >
        <component
          :is="torch.on ? FlashlightOff : Flashlight"
          :size="18"
          aria-hidden="true"
        />
        Lampe
      </UiButton>
    </div>
    <p
      class="text-muted"
      role="status"
    >
      <template v-if="state === 'starting'">
        Ouverture de la caméra…
      </template>
      <template v-else-if="state === 'scanning' && slow">
        Toujours rien&nbsp;: tiens le code bien à plat, à une quinzaine de centimètres, dans un endroit éclairé — ou saisis-le.
      </template>
      <template v-else-if="state === 'scanning'">
        Place le code-barres dans le cadre.
      </template>
      <template v-else-if="state === 'denied'">
        L’accès à la caméra est refusé&nbsp;: autorise-le dans les réglages du navigateur, ou saisis le code.
      </template>
      <template v-else-if="state === 'interrupted'">
        La caméra s’est interrompue.
      </template>
      <template v-else-if="state === 'no-reader'">
        Le lecteur de codes-barres n’a pas pu se charger (connexion&nbsp;?)&nbsp;: saisis le code imprimé sous les barres.
      </template>
      <template v-else>
        Pas de caméra disponible&nbsp;: saisis le code imprimé sous les barres.
      </template>
    </p>
    <UiButton
      v-if="state === 'denied' || state === 'interrupted' || state === 'no-reader'"
      variant="secondary"
      @click="start"
    >
      Réessayer la caméra
    </UiButton>

    <form
      class="flex flex-col gap-2"
      novalidate
      @submit.prevent="submitTyped"
    >
      <UiInput
        ref="typedField"
        v-model="typed"
        label="Code-barres"
        inputmode="numeric"
        autocomplete="off"
        literal
        :maxlength="20"
        hint="8, 12, 13 ou 14 chiffres, imprimés sous les barres."
        :error="typedError"
      />
      <UiButton
        type="submit"
        variant="secondary"
      >
        Rechercher ce code
      </UiButton>
    </form>
  </div>
</template>
