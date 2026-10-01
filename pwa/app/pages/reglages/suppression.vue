<script setup lang="ts">
import DeleteAccountForm from '~/features/data/components/DeleteAccountForm.vue'
import { EXPORT_KEYS, EXPORT_STATUS_LABELS, isUnderWay } from '~/features/data/exports'
import { exportWhen } from '~/features/data/format'
import { daysText, erasureDay, useDataPolicy } from '~/features/data/policy'
import { useExportDownload } from '~/features/data/useExportDownload'

definePageMeta({ title: 'Supprimer mon compte' })

const api = useApi()
const { account } = useAccount()
const { data: policy } = useDataPolicy()
// The latest export, to download before leaving: once the account is closed, it is out of reach.
const { data: exports, refresh } = useAsyncData(EXPORT_KEYS.list, () => api.get('/api/exports'))
const latest = computed(() => exports.value?.[0] ?? null)
const { downloading, message: downloadError, fetchArchive } = useExportDownload(refresh)

const summary = computed(() => `Ton compte est fermé tout de suite, puis effacé${policy.value ? ` ${erasureDay(policy.value)}` : ' plus tard'}.`)
const erased = computed(() => `Ce jour-là, tout est effacé${policy.value ? ` (${daysText(policy.value.deletionDelayDays)} après ta demande)` : ''}\u00A0: profil, repas, séances, pesées, mensurations, photos, recommandations du Coach, notifications et exports.`)
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/reglages/compte"
      label="Compte et sécurité"
    />
    <UiCard title="Ce qui se passe">
      <p
        id="deletion-consequences"
        class="font-semibold"
      >
        {{ summary }}
      </p>
      <ul class="m-0 flex list-disc flex-col gap-2 ps-5">
        <li>Tous tes appareils sont déconnectés.</li>
        <li>{{ erased }}</li>
        <li>D’ici là, te reconnecter annule la suppression. Un email te prévient à chaque étape.</li>
        <li>Après l’effacement, rien ne peut être récupéré.</li>
      </ul>
    </UiCard>
    <UiCard title="Avant de partir">
      <p>
        Récupère d’abord tes données (JSON, CSV, Excel, PDF et photos), et <strong>télécharge l’archive avant de
          confirmer</strong>&nbsp;: une fois le compte fermé, tu ne pourras plus y accéder.
      </p>
      <UiAlert
        v-if="downloadError"
        tone="error"
      >
        {{ downloadError }}
      </UiAlert>
      <div
        v-if="latest"
        class="flex flex-wrap items-center justify-between gap-3"
      >
        <p>
          <span class="font-semibold">Dernier export&nbsp;: {{ EXPORT_STATUS_LABELS[latest.status] }}</span>
          <span class="text-muted"> · {{ exportWhen(latest) }}</span>
        </p>
        <UiButton
          v-if="latest.downloadable"
          variant="secondary"
          :loading="downloading === latest.id"
          @click="fetchArchive(latest)"
        >
          Télécharger<span class="sr-only"> le dernier export</span>
        </UiButton>
      </div>
      <UiAlert
        v-if="latest && isUnderWay(latest.status)"
        tone="info"
      >
        Ton export est encore en préparation&nbsp;: attends qu’il soit prêt et télécharge-le avant de confirmer.
      </UiAlert>
      <UiLinkButton
        to="/reglages/donnees"
        variant="secondary"
      >
        {{ latest ? 'Voir mes exports' : 'Exporter mes données' }}
      </UiLinkButton>
    </UiCard>
    <UiCard
      v-if="account"
      title="Confirmer la suppression"
    >
      <DeleteAccountForm
        :has-password="account.hasPassword"
        :two-factor-enabled="account.twoFactorEnabled"
        consequences="deletion-consequences"
        :reauthentication-minutes="policy?.reauthenticationMinutes"
      />
    </UiCard>
  </div>
</template>
