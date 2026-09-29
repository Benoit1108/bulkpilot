<script setup lang="ts">
const props = defineProps<{ codes: readonly string[] }>()
const emit = defineEmits<{ done: [] }>()

const appName = useRuntimeConfig().public.appName
const saved = ref(false)
const feedback = ref('')
const text = computed(() => `${appName} — codes de secours\n\n${props.codes.join('\n')}\n`)

async function copy(): Promise<void> {
  try {
    await navigator.clipboard.writeText(text.value)
    saved.value = true
    feedback.value = 'Codes copiés.'
  }
  catch {
    feedback.value = 'Copie impossible\u00A0: télécharge le fichier ou note les codes.'
  }
}

function download(): void {
  const url = URL.createObjectURL(new Blob([text.value], { type: 'text/plain' }))
  const link = document.createElement('a')
  link.href = url
  link.download = 'codes-de-secours.txt'
  link.click()
  // Some browsers (Safari) start the download asynchronously.
  setTimeout(() => URL.revokeObjectURL(url), 10_000)
  saved.value = true
  feedback.value = 'Fichier téléchargé.'
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <UiAlert tone="info">
      Garde ces codes en lieu sûr (gestionnaire de mots de passe). Chacun permet une connexion si tu perds ton téléphone.
      Ils ne seront plus affichés.
    </UiAlert>
    <ol
      class="m-0 grid list-none grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] gap-2 rounded-md bg-surface-raised p-3"
      data-testid="recovery-codes"
    >
      <li
        v-for="code in codes"
        :key="code"
      >
        <code class="font-mono tracking-code">{{ code }}</code>
      </li>
    </ol>
    <div class="flex flex-wrap gap-2">
      <UiButton
        variant="secondary"
        @click="copy"
      >
        Copier
      </UiButton>
      <UiButton
        variant="secondary"
        @click="download"
      >
        Télécharger
      </UiButton>
    </div>
    <p
      class="text-sm text-muted"
      role="status"
      data-testid="recovery-feedback"
    >
      {{ feedback }}
    </p>
    <UiButton
      :disabled="!saved"
      @click="emit('done')"
    >
      J’ai enregistré mes codes
    </UiButton>
    <p
      v-if="!saved"
      class="text-sm text-muted"
    >
      Copie ou télécharge les codes pour continuer.
    </p>
  </div>
</template>
