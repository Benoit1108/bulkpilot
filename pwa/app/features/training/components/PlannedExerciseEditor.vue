<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next'
import type { PlannedRow } from '~/features/training/programDraft'
import { STRATEGIES } from '~/features/training/training'

/**
 * One exercise of a block, as the program prescribes it. In a superset only the last exercise
 * carries the rest, taken after the whole round.
 */
const exercise = defineModel<PlannedRow>({ required: true })
const props = defineProps<{
  /** "A", "B1"… */
  label: string
  dayName: string
  inSuperset: boolean
  last: boolean
  error: (field: string) => string | undefined
}>()
const emit = defineEmits<{ remove: [] }>()

const nameId = useId()
const grid = 'grid grid-cols-[repeat(auto-fill,minmax(6.5rem,1fr))] gap-2'
const timed = computed(() => exercise.value.measurement === 'duration')
const unit = computed(() => (timed.value ? 's' : 'reps'))
const rangeLabel = (end: 'min.' | 'max.') => `${timed.value ? 'Durée' : 'Reps'} ${end}`
const restLabel = computed(() => (props.inSuperset ? 'Repos après le tour' : 'Repos'))
const strategyHint = computed(() => STRATEGIES.find(strategy => strategy.value === exercise.value.strategy)?.description)
</script>

<template>
  <div
    class="flex flex-col gap-2 border-t border-line pt-2"
    role="group"
    :aria-labelledby="nameId"
  >
    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2">
      <p
        :id="nameId"
        class="flex gap-2 font-semibold"
        data-testid="planned-name"
      >
        <span>{{ label }}</span>
        {{ exercise.name }}<template v-if="!exercise.available">
          (supprimé)
        </template>
      </p>
      <UiButton
        variant="ghost"
        :aria-label="`Retirer ${exercise.name} de ${dayName}`"
        @click="emit('remove')"
      >
        <Trash2
          :size="18"
          aria-hidden="true"
        />
      </UiButton>
    </div>
    <div :class="grid">
      <UiInput
        v-model="exercise.sets"
        label="Séries"
        type="number"
        inputmode="numeric"
        :error="error('sets')"
      />
      <UiInput
        v-model="exercise.repMin"
        :label="rangeLabel('min.')"
        type="number"
        inputmode="numeric"
        :suffix="unit"
        :error="error('repMin')"
      />
      <UiInput
        v-model="exercise.repMax"
        :label="rangeLabel('max.')"
        type="number"
        inputmode="numeric"
        :suffix="unit"
        :error="error('repMax')"
      />
      <UiInput
        v-model="exercise.targetRir"
        label="RIR"
        type="number"
        inputmode="numeric"
        :error="error('targetRir')"
      />
      <UiInput
        v-if="last"
        v-model="exercise.restSeconds"
        :label="restLabel"
        type="number"
        inputmode="numeric"
        suffix="s"
        :error="error('restSeconds')"
      />
      <p
        v-else
        class="text-sm text-muted"
      >
        Enchaîné sans repos avec le suivant.
      </p>
    </div>
    <details>
      <summary class="flex min-h-tap cursor-pointer items-center font-semibold text-accent-ink">
        Progression, échauffement, tempo, consignes
      </summary>
      <div class="flex flex-col gap-2">
        <UiSelect
          v-model="exercise.strategy"
          label="Progression"
          :nullable="false"
          :options="STRATEGIES"
          :error="error('strategy')"
        />
        <p class="text-sm text-muted">
          {{ strategyHint }}
        </p>
        <div :class="grid">
          <UiInput
            v-if="!timed"
            v-model="exercise.incrementKg"
            label="Palier de charge"
            type="number"
            suffix="kg"
            :error="error('incrementKg')"
          />
          <UiInput
            v-model="exercise.warmupSets"
            label="Échauffement"
            type="number"
            inputmode="numeric"
            suffix="séries"
            :error="error('warmupSets')"
          />
          <UiInput
            v-model="exercise.tempo"
            label="Tempo"
            literal
            :maxlength="15"
            :error="error('tempo')"
          />
        </div>
        <UiCheckbox
          v-model="exercise.dropSet"
          label="Drop set après la dernière série"
        />
        <UiInput
          v-model="exercise.notes"
          label="Consigne pour ce programme"
          :maxlength="200"
          :error="error('notes')"
        />
      </div>
    </details>
  </div>
</template>
