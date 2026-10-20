<script setup lang="ts">
import CoachSettingsForm from '~/features/coach/components/CoachSettingsForm.vue'
import { COACH_KEYS, isCoachDay, type CoachSettings } from '~/features/coach/coach'

definePageMeta({ title: 'Réglages du Coach' })

const api = useApi()
const { data: settings, error, refresh } = useAsyncData(COACH_KEYS.settings, () => api.get('/api/coach/settings'))

function onSaved(saved: CoachSettings): void {
  settings.value = saved
  // Applies from the next analysis (the Coach of the day is read again); what was already said,
  // in the history and the reports, keeps its wording.
  clearNuxtData(isCoachDay)
}
</script>

<template>
  <UiPage>
    <ShellBackLink
      to="/coach"
      label="Coach"
    />
    <UiLoadError
      v-if="error && !settings"
      @retry="refresh()"
    />
    <UiCard v-else-if="settings">
      <CoachSettingsForm
        :settings="settings"
        @saved="onSaved"
      />
    </UiCard>
    <p
      v-else
      class="text-muted"
      role="status"
    >
      Chargement…
    </p>
  </UiPage>
</template>
