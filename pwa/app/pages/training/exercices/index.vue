<script setup lang="ts">
import { Search } from 'lucide-vue-next'
import { TRAINING_KEYS } from '~/features/training/cacheKeys'
import ExerciseIllustration from '~/features/training/components/ExerciseIllustration.vue'
import { EQUIPMENT, MUSCLES, labelOf, type Exercise, type Muscle } from '~/features/training/training'
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
/** By main muscle, in the order of the muscle list: the body read from the chest down. */
const groups = computed(() => MUSCLES
  .map(option => ({ ...option, exercises: found.value.filter(exercise => exercise.primaryMuscle === option.value) }))
  .filter(group => group.exercises.length > 0))
const summary = (exercise: Exercise) => [labelOf(EQUIPMENT, exercise.equipment), exercise.source === 'custom' ? 'perso' : null, exercise.note ? 'consigne' : null].filter(Boolean).join(' · ')
</script>

<template>
  <UiPage layout="columns">
    <template #top>
      <ShellBackLink
        to="/training"
        label="Training"
      />
      <div class="flex flex-wrap items-end gap-3">
        <UiInput
          v-model="query"
          class="min-w-56 flex-1"
          label="Rechercher un exercice"
          autocomplete="off"
        />
        <UiSelect
          v-model="muscle"
          class="min-w-56 flex-1"
          label="Muscle"
          placeholder="Tous les muscles"
          :options="MUSCLES"
        />
        <UiLinkButton to="/training/exercices/nouveau">
          Nouvel exercice
        </UiLinkButton>
      </div>
      <p
        v-if="exercises"
        class="text-sm text-muted"
        role="status"
      >
        {{ found.length }}&nbsp;exercice{{ found.length > 1 ? 's' : '' }}
      </p>
    </template>
    <UiLoadError
      v-if="error && !exercises"
      @retry="refresh()"
    />
    <template v-else-if="exercises">
      <section
        v-for="group in groups"
        :key="group.value"
        class="flex flex-col gap-2"
      >
        <h2 class="px-1 text-lg font-semibold">
          {{ group.label }}
        </h2>
        <ul
          class="divide-y divide-line overflow-hidden rounded-lg bg-surface"
          role="list"
        >
          <li
            v-for="exercise in group.exercises"
            :key="exercise.id"
          >
            <NuxtLink
              :to="`/training/exercices/${exercise.id}`"
              class="flex min-h-tap items-center gap-3 px-3 py-2 text-ink no-underline transition-colors hover:bg-surface-raised focus-visible:-outline-offset-2"
            >
              <ExerciseIllustration
                :library-key="exercise.libraryKey"
                :name="exercise.name"
                :primary="exercise.primaryMuscle"
                :secondary="exercise.secondaryMuscles"
                thumbnail
              />
              <span class="flex min-w-0 flex-col">
                <span class="font-semibold">{{ exercise.name }}</span>
                <span class="text-sm text-muted">{{ summary(exercise) }}</span>
              </span>
            </NuxtLink>
          </li>
        </ul>
      </section>
      <UiCard v-if="!groups.length">
        <UiEmptyState
          :icon="Search"
          title="Aucun exercice ne correspond"
          description="Change la recherche ou le muscle, ou crée l’exercice qui manque."
        />
      </UiCard>
    </template>
  </UiPage>
</template>
