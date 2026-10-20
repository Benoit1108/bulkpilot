<script setup lang="ts">
import GoalForm from '~/features/progression/components/GoalForm.vue'
import { isWeightDerived, PROGRESSION_KEYS } from '~/features/progression/progression'
import { asApiError } from '~/services/api/client'
import { localDay } from '~/services/calendar/days'

definePageMeta({ title: 'Objectif de poids' })

const api = useApi()
const today = localDay()
const { data: goal, error, refresh } = useAsyncData(PROGRESSION_KEYS.goal, async () => {
  try {
    return await api.get('/api/progression/weight-goal', { query: { today } })
  }
  catch (caught) {
    // No goal yet: the form starts empty.
    if (asApiError(caught).kind === 'not_found') return null
    throw caught
  }
})

async function onSaved(): Promise<void> {
  clearNuxtData(isWeightDerived)
  await navigateTo('/progression')
}

const ending = useSubmission()
async function end(): Promise<void> {
  if (await ending.run(() => api.delete('/api/progression/weight-goal'))) await onSaved()
}
</script>

<template>
  <UiPage layout="columns">
    <template #top>
      <ShellBackLink
        to="/progression"
        label="Progression"
      />
    </template>
    <UiLoadError
      v-if="error && goal === undefined"
      @retry="refresh()"
    />
    <template v-else-if="goal !== undefined">
      <UiCard>
        <GoalForm
          :goal="goal"
          @saved="onSaved"
        />
      </UiCard>
      <UiCard v-if="goal">
        <UiAlert
          v-if="ending.message.value"
          tone="error"
        >
          {{ ending.message.value }}
        </UiAlert>
        <p class="text-sm text-muted">
          Ne plus suivre d’objectif&nbsp;: tes pesées restent, l’objectif est gardé dans l’historique.
        </p>
        <UiConfirmButton
          label="Ne plus suivre cet objectif"
          confirm-label="Confirmer"
          :loading="ending.pending.value"
          @confirm="end"
        />
      </UiCard>
    </template>
  </UiPage>
</template>
