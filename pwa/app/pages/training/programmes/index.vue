<script setup lang="ts">
import { ListChecks } from 'lucide-vue-next'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'

definePageMeta({ title: 'Mes programmes' })

const api = useApi()
const { data: programs, error, refresh } = useAsyncData(TRAINING_KEYS.programs, () => api.get('/api/programs'))
const items = computed(() => (programs.value ?? []).map(program => ({
  to: `/training/programmes/${program.id}`,
  title: program.name,
  summary: [
    program.active ? 'Suivi' : null,
    `version ${program.currentVersion}`,
    program.days.map(day => day.name).join(', '),
  ].filter(Boolean).join(' · '),
})))
</script>

<template>
  <UiPage>
    <ShellBackLink
      to="/training"
      label="Training"
    />
    <UiLinkButton
      to="/training/programmes/nouveau"
    >
      Nouveau programme
    </UiLinkButton>
    <UiLoadError
      v-if="error && !programs"
      @retry="refresh()"
    />
    <UiNavList
      v-else-if="items.length"
      label="Mes programmes"
      :items="items"
    />
    <UiCard v-else-if="programs">
      <UiEmptyState
        :icon="ListChecks"
        title="Aucun programme"
        description="Un programme regroupe tes séances&nbsp;: PPL, Full Body, Vacances… Un seul est suivi à la fois."
      />
    </UiCard>
  </UiPage>
</template>
