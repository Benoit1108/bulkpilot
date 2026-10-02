<script setup lang="ts">
import { useWorkoutOutbox } from '~/features/training/outbox'
import { deviceOwner } from '~/services/offline/owner'

/**
 * What the device still has to send (spec §117): nothing while all goes through, a quiet line
 * once changes wait for a while (offline, or a slow server), and the ones the API refused, with a
 * choice. Changes are announced when waiting starts, when the session turns out expired and when
 * all is sent (said on screen for a moment too), not at every set.
 */
const outbox = useWorkoutOutbox()
const state = outbox.state
const acting = ref(false)
/** Shown after a few seconds: online, a change is sent before anyone notices. */
const WAIT_MS = 3000
/** « Tout est envoyé » stays on screen this long. */
const SENT_MS = 2500
const waiting = ref(false)
const sentShown = ref(false)
const announcement = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
let sentTimer: ReturnType<typeof setTimeout> | undefined

const { account } = useAccount()
// Known to be signed out: opened offline, the account is just not known yet (undefined).
const signedOut = computed(() => state.pending > 0 && !deviceOwner() && account.value === null)
const offline = () => import.meta.client && !navigator.onLine
const changes = (count: number) => `${count}\u00A0modification${count > 1 ? 's' : ''}`

watch(() => state.pending > 0, (pending) => {
  clearTimeout(timer)
  if (pending) {
    sentShown.value = false
    timer = setTimeout(() => {
      waiting.value = true
      announcement.value = signedOut.value
        ? expired()
        : offline() ? 'Hors ligne\u00A0: tes séries sont gardées sur cet appareil.' : 'Pas encore envoyé\u00A0: gardé sur cet appareil.'
    }, WAIT_MS)
    return
  }
  if (waiting.value) {
    announcement.value = 'Tout est envoyé.'
    sentShown.value = true
    clearTimeout(sentTimer)
    sentTimer = setTimeout(() => (sentShown.value = false), SENT_MS)
  }
  waiting.value = false
}, { immediate: true })
const expired = () => `Session expirée\u00A0: reconnecte-toi pour envoyer ${changes(state.pending)}.`
// Known to be expired after the line showed (the account read again once back online).
watch(signedOut, (now) => {
  if (now && waiting.value) announcement.value = expired()
})
onBeforeUnmount(() => {
  clearTimeout(timer)
  clearTimeout(sentTimer)
})

async function act(action: () => Promise<void>): Promise<void> {
  acting.value = true
  try {
    await action()
  }
  finally {
    acting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col gap-2">
    <p
      class="sr-only"
      role="status"
    >
      {{ announcement }}
    </p>
    <p
      v-if="waiting && state.pending"
      class="text-sm text-muted"
    >
      <template v-if="signedOut">
        Session expirée&nbsp;: {{ state.pending }}&nbsp;modification{{ state.pending > 1 ? 's' : '' }} attend{{ state.pending > 1 ? 'ent' : '' }} ta reconnexion pour être envoyée{{ state.pending > 1 ? 's' : '' }}.
      </template>
      <template v-else-if="offline()">
        Hors ligne&nbsp;: {{ state.pending }}&nbsp;modification{{ state.pending > 1 ? 's' : '' }} gardée{{ state.pending > 1 ? 's' : '' }} sur cet appareil, envoyée{{ state.pending > 1 ? 's' : '' }} au retour du réseau.
      </template>
      <template v-else>
        Pas encore envoyé&nbsp;: {{ state.pending }}&nbsp;modification{{ state.pending > 1 ? 's' : '' }} gardée{{ state.pending > 1 ? 's' : '' }} sur cet appareil.
      </template>
    </p>
    <p
      v-else-if="sentShown"
      class="text-sm text-muted"
      aria-hidden="true"
    >
      Tout est envoyé.
    </p>
    <UiAlert
      v-if="state.failed.length"
      tone="error"
      :focus-on-mount="false"
    >
      <p>
        {{ state.failed.length }}&nbsp;modification{{ state.failed.length > 1 ? 's n’ont' : ' n’a' }} pas pu être enregistrée{{ state.failed.length > 1 ? 's' : '' }}&nbsp;: {{ state.failed[0]?.message }}
      </p>
      <div class="mt-2 flex flex-wrap gap-2">
        <UiButton
          variant="secondary"
          :loading="acting"
          @click="act(outbox.retryFailed)"
        >
          Réessayer
        </UiButton>
        <UiConfirmButton
          label="Les ignorer"
          confirm-label="Confirmer (elles seront perdues)"
          variant="ghost"
          @confirm="act(outbox.dropFailed)"
        />
      </div>
    </UiAlert>
  </div>
</template>
