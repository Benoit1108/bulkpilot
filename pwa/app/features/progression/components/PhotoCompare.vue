<script setup lang="ts">
import { PILL } from '~/components/ui/classes'
import { formatFullDay } from '~/services/calendar/days'
import { formatCm, formatKg, formatSignedCm, formatSignedKg } from '../format'
import { measureName, poseLabel, POSES, type PhotoSession, type Pose } from '../progression'

/**
 * Two sessions face to face (spec §60–61: « Début — 45 kg vs Aujourd'hui — 51,2 kg »), side by
 * side or one over the other with a slider, with what changed in between.
 */
const props = defineProps<{ sessions: PhotoSession[] }>()

const days = computed(() => props.sessions.map(session => session.day))
const before = ref(days.value.at(-1) ?? '')
const after = ref(days.value[0] ?? '')
const mode = ref<'side' | 'slider'>('side')
const pose = ref<Pose>('front')
const reveal = ref(50)

const sessionOn = (day: string) => props.sessions.find(session => session.day === day) ?? null
const label = (session: PhotoSession) => `${formatFullDay(session.day)}${session.weight ? ` — ${formatKg(session.weight.weightKg)}` : ''}`
const options = computed(() => props.sessions.map(session => ({ value: session.day, label: label(session) })))
const poses = computed(() => POSES.filter(option => [before.value, after.value].every(day => sessionOn(day)?.photos.some(photo => photo.pose === option.value))))
watch(poses, (available) => {
  if (available.length && !available.some(option => option.value === pose.value)) pose.value = available[0]!.value
}, { immediate: true })

const photos = computed(() => [before.value, after.value].map((day) => {
  const session = sessionOn(day)
  return session ? { session, photo: session.photos.find(photo => photo.pose === pose.value) ?? null } : null
}))
const pair = computed(() => (photos.value[0]?.photo && photos.value[1]?.photo ? { before: photos.value[0], after: photos.value[1] } : null))

/** What changed between the two days: the weight, and each measure taken on both. */
const changes = computed(() => {
  const [first, last] = [sessionOn(before.value), sessionOn(after.value)]
  if (!first || !last) return []
  const lines: string[] = []
  if (first.weight && last.weight) lines.push(`Poids\u00A0: ${formatSignedKg(last.weight.weightKg - first.weight.weightKg)}`)
  const was = new Map((first.measurements?.values ?? []).map(value => [value.key, value.cm]))
  for (const value of last.measurements?.values ?? []) {
    const then = was.get(value.key)
    if (then !== undefined) lines.push(`${measureName(value)}\u00A0: ${formatSignedCm(value.cm - then)} (${formatCm(value.cm)})`)
  }
  return lines
})
const clip = computed(() => ({ clipPath: `inset(0 0 0 ${reveal.value}%)` }))
const divider = computed(() => ({ left: `${reveal.value}%` }))
const revealText = computed(() => `${reveal.value}\u00A0% de l’avant visible`)
const sliderId = useId()
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="grid gap-3 sm:grid-cols-2">
      <UiSelect
        v-model="before"
        label="Avant"
        :options="options"
        :nullable="false"
      />
      <UiSelect
        v-model="after"
        label="Après"
        :options="options"
        :nullable="false"
      />
    </div>
    <div class="flex flex-wrap gap-4">
      <fieldset class="m-0 min-w-0 border-0 p-0">
        <legend class="mb-1 text-sm font-semibold">
          Pose
        </legend>
        <div class="flex flex-wrap gap-2">
          <label
            v-for="option in poses"
            :key="option.value"
            :class="PILL"
          >
            <input
              v-model="pose"
              type="radio"
              name="compare-pose"
              :value="option.value"
              class="sr-only"
            >
            {{ option.label }}
          </label>
        </div>
      </fieldset>
      <fieldset class="m-0 min-w-0 border-0 p-0">
        <legend class="mb-1 text-sm font-semibold">
          Affichage
        </legend>
        <div class="flex flex-wrap gap-2">
          <label :class="PILL">
            <input
              v-model="mode"
              type="radio"
              name="compare-mode"
              value="side"
              class="sr-only"
            >
            Côte à côte
          </label>
          <label :class="PILL">
            <input
              v-model="mode"
              type="radio"
              name="compare-mode"
              value="slider"
              class="sr-only"
            >
            Curseur
          </label>
        </div>
      </fieldset>
    </div>
    <p
      v-if="!pair"
      class="text-muted"
    >
      Ces deux jours n’ont pas de photo de la même pose.
    </p>
    <div
      v-else-if="mode === 'side'"
      class="grid grid-cols-2 gap-2"
    >
      <figure
        v-for="side in [pair.before, pair.after]"
        :key="side.session.day"
        class="m-0 flex flex-col gap-1"
      >
        <img
          :src="side.photo!.url"
          :alt="`${poseLabel(pose)}, ${label(side.session)}`"
          class="aspect-3/4 h-auto w-full rounded-md object-cover"
        >
        <figcaption class="text-sm">
          {{ label(side.session) }}
        </figcaption>
      </figure>
    </div>
    <div
      v-else
      class="flex flex-col gap-2"
    >
      <div class="relative overflow-hidden rounded-md">
        <img
          :src="pair.before.photo!.url"
          :alt="`${poseLabel(pose)}, ${label(pair.before.session)}`"
          class="block aspect-3/4 h-auto w-full object-cover"
        >
        <img
          :src="pair.after.photo!.url"
          :alt="`${poseLabel(pose)}, ${label(pair.after.session)}`"
          class="absolute inset-0 aspect-3/4 size-full object-cover"
          :style="clip"
        >
        <!-- Where one photo ends and the other begins. -->
        <div
          class="pointer-events-none absolute inset-y-0 w-1 -translate-x-1/2 border-x border-ink/40 bg-surface"
          :style="divider"
          aria-hidden="true"
        />
      </div>
      <label
        :for="sliderId"
        class="text-sm font-semibold"
      >Curseur (à gauche l’avant, à droite l’après)</label>
      <input
        :id="sliderId"
        v-model.number="reveal"
        type="range"
        min="0"
        max="100"
        class="min-h-tap w-full accent-accent-fill"
        :aria-valuetext="revealText"
      >
      <p class="flex justify-between text-sm">
        <span>{{ label(pair.before.session) }}</span>
        <span>{{ label(pair.after.session) }}</span>
      </p>
    </div>
    <ul
      v-if="changes.length"
      class="m-0 flex list-none flex-col gap-1 p-0 text-sm"
    >
      <li
        v-for="change in changes"
        :key="change"
      >
        {{ change }}
      </li>
    </ul>
  </div>
</template>
