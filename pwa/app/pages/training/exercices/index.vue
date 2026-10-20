<script setup lang="ts">
import { Search } from 'lucide-vue-next'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import { EQUIPMENT, MUSCLES, labelOf, type Muscle } from '~/features/training/training'
import { matches } from '~/services/text/search'

definePageMeta({ title: 'Exercices' })

const api = useApi()
const { data: exercises, error, refresh } = useAsyncData(TRAINING_KEYS.exercises, () => api.get('/api/exercises'))
const query = ref('')
const muscle = ref<Muscle | null>(null)

const found = computed(() => (exercises.value ?? []).filter(exercise =>
  matches(exercise.name, query.value)
  && (muscle.value === null || exercise.primaryMuscle === muscle.value || exercise.secondaryMuscles.includes(muscle.value)),
))
const items = computed(() => found.value.map(exercise => ({
  to: `/training/exercices/${exercise.id}`,
  title: exercise.name,
  summary: [labelOf(MUSCLES, exercise.primaryMuscle), labelOf(EQUIPMENT, exercise.equipment), exercise.source === 'custom' ? 'perso' : null, exercise.note ? 'consigne' : null].filter(Boolean).join(' · '),
})))
</script>

<template>
  <UiPage>
    <ShellBackLink
      to="/training"
      label="Training"
    />
    <UiLinkButton
      to="/training/exercices/nouveau"
    >
      Nouvel exercice
    </UiLinkButton>
    <div class="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-3">
      <UiInput
        v-model="query"
        label="Rechercher un exercice"
        autocomplete="off"
      />
      <UiSelect
        v-model="muscle"
        label="Muscle"
        placeholder="Tous les muscles"
        :options="MUSCLES"
      />
    </div>
    <UiLoadError
      v-if="error && !exercises"
      @retry="refresh()"
    />
    <template v-else-if="exercises">
      <p
        class="text-sm text-muted"
        role="status"
      >
        {{ found.length }}&nbsp;exercice{{ found.length > 1 ? 's' : '' }}
      </p>
      <UiNavList
        v-if="items.length"
        label="Exercices"
        :items="items"
      />
      <UiCard v-else>
        <UiEmptyState
          :icon="Search"
          title="Aucun exercice ne correspond"
          description="Change la recherche ou le muscle, ou crée l’exercice qui manque."
        />
      </UiCard>
    </template>
  </UiPage>
</template>
