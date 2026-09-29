<script setup lang="ts">
import { formatFullDay } from '~/services/calendar/days'
import { amountText, optionalAmount, type Amount } from '~/services/text/numbers'
import { formatKg } from '../format'
import { WEIGHT_KG, type WeighIn, type WeightGoal } from '../progression'

/**
 * The target and the milestones on the way (spec §52), in any order: the API sorts them the way
 * the weight goes, from the latest weigh-in.
 */
const props = defineProps<{ goal: WeightGoal | null }>()
const emit = defineEmits<{ saved: [goal: WeightGoal] }>()

const MAX_MILESTONES = 10
const api = useApi()
const { pending, message, fields, run } = useSubmission()

let nextRow = 0
const target = ref<Amount>(props.goal ? amountText(props.goal.targetKg) : '')
// A new goal starts from the latest weigh-in: the milestones already reached are behind it.
const milestones = ref(props.goal?.steps.filter(step => !step.target && !step.reachedOn).map(step => ({ row: nextRow++, kg: amountText(step.weightKg) as Amount })) ?? [])
const full = computed(() => milestones.value.length >= MAX_MILESTONES)

// The start of the goal saved is the latest weigh-in: shown so the target is picked from it.
const start = ref<WeighIn | null | undefined>(undefined)
onMounted(async () => {
  try {
    start.value = (await api.get('/api/progression/weigh-ins', { query: { limit: 1 } }))[0] ?? null
  }
  catch {
    // Offline or refused: the hint stays generic.
  }
})
const startHint = computed(() => {
  if (start.value === undefined) return 'Le départ est ta dernière pesée.'
  if (start.value === null) return 'Le départ est ta dernière pesée\u00A0: enregistres-en une d’abord.'
  return `Départ\u00A0: ta dernière pesée, ${formatKg(start.value.weightKg)} le ${formatFullDay(start.value.day)}.`
})

function body() {
  return {
    targetKg: optionalAmount(target.value),
    milestonesKg: milestones.value.map(milestone => optionalAmount(milestone.kg)).filter((kg): kg is number => kg !== undefined),
  }
}
const { markSaved } = useUnsavedChanges(body)

const list = ref<HTMLElement | null>(null)
function add(): void {
  milestones.value.push({ row: nextRow++, kg: '' })
  void nextTick(() => list.value?.querySelector<HTMLInputElement>('li:last-child input')?.focus())
}
const addButton = ref<{ $el: HTMLElement } | null>(null)
function remove(index: number): void {
  milestones.value.splice(index, 1)
  void nextTick(() => addButton.value?.$el.focus())
}

async function submit(): Promise<void> {
  const sent = body()
  await run(async () => {
    const saved = await api.put('/api/progression/weight-goal', sent as { targetKg: number, milestonesKg: number[] })
    markSaved(sent)
    emit('saved', saved)
  })
}
</script>

<template>
  <form
    class="flex flex-col gap-4"
    novalidate
    @submit.prevent="submit"
  >
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiInput
      v-model="target"
      label="Poids visé"
      type="number"
      suffix="kg"
      required
      :min="WEIGHT_KG.min"
      :max="WEIGHT_KG.max"
      :hint="startHint"
      :error="fields.targetKg"
    />
    <fieldset class="m-0 flex min-w-0 flex-col gap-2 border-0 p-0">
      <legend class="mb-1 text-sm font-semibold">
        Jalons sur le chemin <span class="font-normal text-muted">(facultatifs, {{ MAX_MILESTONES }} au plus)</span>
      </legend>
      <ol
        v-if="milestones.length"
        ref="list"
        class="m-0 flex list-none flex-col gap-2 p-0"
      >
        <li
          v-for="(milestone, i) in milestones"
          :key="milestone.row"
          class="flex items-end gap-2"
        >
          <UiInput
            v-model="milestone.kg"
            class="flex-1"
            :label="`Jalon ${i + 1}`"
            type="number"
            suffix="kg"
            :min="WEIGHT_KG.min"
            :max="WEIGHT_KG.max"
            :error="fields[`milestonesKg[${i}]`]"
          />
          <UiButton
            variant="ghost"
            :aria-label="`Retirer le jalon ${i + 1}`"
            @click="remove(i)"
          >
            Retirer
          </UiButton>
        </li>
      </ol>
      <UiButton
        ref="addButton"
        variant="secondary"
        class="self-start"
        :disabled="full"
        @click="add"
      >
        Ajouter un jalon
      </UiButton>
    </fieldset>
    <div class="flex flex-wrap gap-2">
      <UiButton
        type="submit"
        :loading="pending"
      >
        {{ goal ? 'Remplacer l’objectif' : 'Suivre cet objectif' }}
      </UiButton>
    </div>
  </form>
</template>
