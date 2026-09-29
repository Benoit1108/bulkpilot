<script setup lang="ts">
import { localDay } from '~/services/calendar/days'
import { amountText, parseAmount } from '~/services/text/numbers'
import { formatKg } from '../format'
import { WEIGHT_KG, type WeighIn } from '../progression'

/**
 * Today's weigh-in, or another day's (filled in afterwards). One a day: the day's weigh-in, if
 * any, is replaced.
 */
const props = defineProps<{ latest: WeighIn | { day: string, weightKg: number } | null }>()
const emit = defineEmits<{ saved: [weighIn: WeighIn] }>()

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
const saved = ref<string | null>(null)

async function submit(): Promise<void> {
  saved.value = null
  const kg = parseAmount(weight.value)
  await run(async () => {
    const weighIn = await api.put('/api/progression/weigh-ins/{day}', { weightKg: kg ?? undefined } as { weightKg: number }, { path: { day: day.value } })
    saved.value = `${day.value === today ? 'Pesée du jour' : 'Pesée'} enregistrée\u00A0: ${formatKg(weighIn.weightKg)}.`
    emit('saved', weighIn)
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
        v-model="weight"
        label="Poids"
        type="number"
        suffix="kg"
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
    <div class="flex flex-wrap items-center gap-3">
      <UiButton
        type="submit"
        :loading="pending"
      >
        Enregistrer la pesée
      </UiButton>
      <UiStatus
        :message="saved"
        @dismiss="saved = null"
      />
    </div>
  </form>
</template>
