<script setup lang="ts">
import { formatShortDay, localDay } from '~/services/calendar/days'
import { amountText, optionalAmount, type Amount } from '~/services/text/numbers'
import { formatCm } from '../format'
import { LENGTH_CM, measureName, type Measure, type Measurements, type MeasureSeries } from '../progression'

/**
 * The measurements of a day (spec §54): any of the measures, the others left blank. The day's
 * measurements, if any, fill the form and are replaced as a whole.
 */
const props = defineProps<{ measures: Measure[], series: MeasureSeries[] }>()
const emit = defineEmits<{ saved: [measurements: Measurements] }>()

const api = useApi()
const { pending, message, fields, run } = useSubmission()
const today = localDay()
const day = ref(today)
const values = ref<Record<string, Amount>>({})
const replacing = ref(false)
const saved = ref<string | null>(null)

const latest = computed(() => new Map(props.series.map(series => [series.key, series.latest])))
const hint = (key: string) => {
  const last = latest.value.get(key)
  return last ? `Dernière\u00A0: ${formatCm(last.cm)} (${formatShortDay(last.day)})` : undefined
}

function body() {
  const centimetres: Record<string, number> = {}
  for (const measure of props.measures) {
    const cm = optionalAmount(values.value[measure.key] ?? null)
    if (cm !== undefined) centimetres[measure.key] = cm
  }
  return { centimetres }
}
const { markSaved } = useUnsavedChanges(body)

/** The day's own measurements, when it has some: they are what is edited. */
watch(day, async (picked) => {
  values.value = {}
  replacing.value = false
  let found: Record<string, number> | null = null
  try {
    found = (await api.get('/api/progression/measurements/{day}', { path: { day: picked } })).centimetres
  }
  catch {
    // None that day (404), or offline: the form starts blank.
  }
  // Another day was picked meanwhile: its own answer fills the form, not this one.
  if (day.value !== picked) return
  if (found) {
    values.value = Object.fromEntries(Object.entries(found).map(([key, cm]) => [key, amountText(cm)]))
    replacing.value = true
  }
  markSaved(body())
}, { immediate: true })

async function submit(): Promise<void> {
  saved.value = null
  const sent = body()
  await run(async () => {
    const measurements = await api.put('/api/progression/measurements/{day}', sent, { path: { day: day.value } })
    markSaved(sent)
    replacing.value = true
    saved.value = `Mensurations enregistrées (${Object.keys(measurements.centimetres).length}).`
    emit('saved', measurements)
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
      v-model="day"
      label="Jour"
      type="date"
      required
      :max="today"
      :hint="replacing ? 'Les mensurations de ce jour-là sont affichées\u00A0: les enregistrer les remplace.' : undefined"
    />
    <div class="grid grid-cols-2 gap-3 *:min-w-0">
      <UiInput
        v-for="measure in measures"
        :key="measure.key"
        v-model="values[measure.key]"
        :label="measureName(measure)"
        type="number"
        suffix="cm"
        :min="LENGTH_CM.min"
        :max="LENGTH_CM.max"
        :hint="hint(measure.key)"
        :error="fields[`centimetres[${measure.key}]`]"
      />
    </div>
    <div class="flex flex-wrap items-center gap-3">
      <UiButton
        type="submit"
        :loading="pending"
      >
        Enregistrer les mensurations
      </UiButton>
      <UiStatus
        :message="saved"
        @dismiss="saved = null"
      />
    </div>
  </form>
</template>
