<script setup lang="ts">
import { ArrowDown, ArrowUp } from 'lucide-vue-next'
import { MAX_BLOCKS, MAX_IN_BLOCK, type BlockRow, type DayRow } from '~/features/training/programDraft'
import { blockLabel, blockLetter } from '~/features/training/training'
import PlannedExerciseEditor from './PlannedExerciseEditor.vue'

/** One day of the program: its name and its blocks in order, each one a single exercise or a superset. */
const day = defineModel<DayRow>({ required: true })
const props = defineProps<{
  index: number
  removable: boolean
  /** API errors of the whole form, by property path. */
  fields: Record<string, string>
}>()
const emit = defineEmits<{
  remove: []
  /** Pick an exercise for a new block (null) or for that block (superset). */
  pick: [block: number | null]
  move: [block: number, by: -1 | 1]
  removeExercise: [block: number, exercise: number]
}>()

const dayName = computed(() => day.value.name || `Jour ${props.index + 1}`)
const error = (b: number, e: number) => (field: string) => props.fields[`days[${props.index}].blocks[${b}].exercises[${e}].${field}`]
const isSuperset = (block: BlockRow) => block.exercises.length > 1
const blockTitle = (block: BlockRow, b: number) => `${isSuperset(block) ? 'Superset' : 'Bloc'} ${blockLetter(b)}`
const canGrow = (block: BlockRow) => block.exercises.length < MAX_IN_BLOCK
const isLastBlock = (b: number) => b === day.value.blocks.length - 1
const isLastIn = (block: BlockRow, e: number) => e === block.exercises.length - 1
const full = computed(() => day.value.blocks.length >= MAX_BLOCKS)
</script>

<template>
  <fieldset class="m-0 flex min-w-0 flex-col gap-3 rounded-md border border-line p-3">
    <legend class="sr-only">
      {{ dayName }}
    </legend>
    <div
      class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2"
      :data-day-name="day.row"
    >
      <UiInput
        v-model="day.name"
        :label="`Nom du jour ${index + 1}`"
        :maxlength="40"
        hint="Par exemple «&nbsp;Push&nbsp;» ou «&nbsp;Jambes&nbsp;»."
        :error="fields[`days[${index}].name`]"
      />
      <UiConfirmButton
        v-if="removable"
        label="Retirer ce jour"
        confirm-label="Confirmer"
        variant="ghost"
        @confirm="emit('remove')"
      />
    </div>
    <p
      v-if="fields[`days[${index}].blocks`]"
      class="text-sm text-danger"
    >
      {{ fields[`days[${index}].blocks`] }}
    </p>

    <ol class="flex flex-col gap-3">
      <li
        v-for="(block, b) in day.blocks"
        :key="block.row"
        class="flex flex-col gap-2"
        :class="{ 'border-l-3 border-accent pl-2': isSuperset(block) }"
      >
        <div class="flex items-center justify-between gap-2">
          <span class="text-xs font-semibold tracking-caps text-muted uppercase">{{ blockTitle(block, b) }}</span>
          <span class="flex">
            <UiButton
              variant="ghost"
              :disabled="b === 0"
              :data-move="`${block.row}--1`"
              :aria-label="`Monter ${block.exercises[0]?.name}`"
              @click="emit('move', b, -1)"
            >
              <ArrowUp
                :size="18"
                aria-hidden="true"
              />
            </UiButton>
            <UiButton
              variant="ghost"
              :disabled="isLastBlock(b)"
              :data-move="`${block.row}-1`"
              :aria-label="`Descendre ${block.exercises[0]?.name}`"
              @click="emit('move', b, 1)"
            >
              <ArrowDown
                :size="18"
                aria-hidden="true"
              />
            </UiButton>
          </span>
        </div>

        <PlannedExerciseEditor
          v-for="(exercise, e) in block.exercises"
          :key="exercise.row"
          v-model="block.exercises[e]!"
          :label="blockLabel(b, e, block.exercises.length)"
          :day-name="day.name"
          :in-superset="isSuperset(block)"
          :last="isLastIn(block, e)"
          :error="error(b, e)"
          @remove="emit('removeExercise', b, e)"
        />

        <UiButton
          v-if="canGrow(block)"
          variant="ghost"
          @click="emit('pick', b)"
        >
          {{ isSuperset(block) ? 'Ajouter au superset' : 'Faire un superset' }}<span class="sr-only"> avec {{ block.exercises[0]?.name }}</span>
        </UiButton>
      </li>
    </ol>

    <UiButton
      variant="secondary"
      :disabled="full"
      :data-add="day.row"
      @click="emit('pick', null)"
    >
      Ajouter un exercice à {{ day.name || 'ce jour' }}
    </UiButton>
  </fieldset>
</template>
