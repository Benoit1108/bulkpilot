<script setup lang="ts">
import { useWorkoutOutbox } from '~/features/training/outbox'
import { deviceOwner } from '~/services/offline/owner'

/**
 * What the device still has to send (spec §117): nothing while all goes through, a quiet line
 * once changes wait for a while (offline), and the ones the API refused, with a choice. Changes
 * are announced when waiting starts and when all is sent, not at every set.
 */
const outbox = useWorkoutOutbox()
const state = outbox.state
const acting = ref(false)
/** Shown after a few seconds: online, a change is sent before anyone notices. */
const WAIT_MS = 3000
const waiting = ref(false)
const announcement = ref('')
let timer: ReturnType<typeof setTimeout> | undefined

watch(() => state.pending > 0, (pending) => {
  clearTimeout(timer)
  if (pending) {
    timer = setTimeout(() => {
      waiting.value = true
      announcement.value = 'Hors ligne\u00A0: tes séries sont gardées sur le téléphone.'
    }, WAIT_MS)
    return
  }
  if (waiting.value) announcement.value = 'Tout est envoyé.'
  waiting.value = false
}, { immediate: true })
onBeforeUnmount(() => clearTimeout(timer))

const signedOut = computed(() => state.pending > 0 && !deviceOwner())

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
      <template v-else>
        {{ state.pending }}&nbsp;modification{{ state.pending > 1 ? 's' : '' }} gardée{{ state.pending > 1 ? 's' : '' }} sur le téléphone, envoyée{{ state.pending > 1 ? 's' : '' }} au retour du réseau.
      </template>
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
