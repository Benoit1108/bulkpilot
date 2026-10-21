<script setup lang="ts">
import { localDay } from '~/services/calendar/days'
import { amountText, parseAmount } from '~/services/text/numbers'
import { formatKg } from '../format'
import { WEIGHT_KG, type WeighIn } from '../progression'

/**
 * Today's weigh-in, or another day's (filled in afterwards). One a day: the day's weigh-in, if
 * any, is replaced. It sits in a panel that closes once saved: what was saved is said by the page.
 */
const props = defineProps<{ latest: WeighIn | { day: string, weightKg: number } | null }>()
const emit = defineEmits<{ saved: [message: string] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const today = localDay()
const day = ref(today)
const shown = (kg: number | undefined) => (kg === undefined ? '' : amountText(kg))
const weight = ref<string | number | null>(shown(props.latest?.weightKg))
// The latest weigh-in arrives (or changes) after the form: it fills the field until the user types.
watch(() => props.latest?.weightKg, (kg, before) => {
  if (kg !== undefined && (weight.value === '' || weight.value === shown(before))) weight.value = shown(kg)
})
const replacing = computed(() => props.latest?.day === day.value)
const weightField = ref<{ focus: () => void } | null>(null)
/** The global « + » leads to the form: the weight field takes the focus. */
defineExpose({ focus: () => weightField.value?.focus() })

async function submit(): Promise<void> {
  const kg = parseAmount(weight.value)
  await run(async () => {
    const weighIn = await api.put('/api/progression/weigh-ins/{day}', { weightKg: kg ?? undefined } as { weightKg: number }, { path: { day: day.value } })
    emit('saved', `${day.value === today ? 'Pesée du jour' : 'Pesée'} enregistrée\u00A0: ${formatKg(weighIn.weightKg)}.`)
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
    <div class="grid grid-cols-2 gap-3">
      <UiInput
        ref="weightField"
        v-model="weight"
        label="Poids"
        type="number"
        suffix="kg"
        autofocus
        required
        :min="WEIGHT_KG.min"
        :max="WEIGHT_KG.max"
        :error="fields.weightKg"
      />
      <UiInput
        v-model="day"
        label="Jour"
        type="date"
        required
        :max="today"
        :hint="replacing ? 'Une pesée existe déjà ce jour-là\u00A0: elle sera remplacée.' : undefined"
      />
    </div>
    <UiButton
      type="submit"
      class="self-start"
      :loading="pending"
    >
      Enregistrer la pesée
    </UiButton>
  </form>
</template>
