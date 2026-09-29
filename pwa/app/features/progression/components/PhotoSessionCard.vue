<script setup lang="ts">
import { formatFullDay } from '~/services/calendar/days'
import { formatCm, formatKg } from '../format'
import { measureName, poseLabel, type PhotoSession, type ProgressPhoto } from '../progression'

/**
 * The photos of a day (spec §61), with the weight and the measurements of that day (or the latest
 * before it): each one to open, download or delete for good.
 */
const props = defineProps<{ session: PhotoSession }>()
const emit = defineEmits<{ removed: [photo: ProgressPhoto] }>()

const api = useApi()
const removal = useSubmission()
// The photo shown is found again in the session: renewed links reach the open drawer too.
const openedId = ref<string | null>(null)
const opened = computed(() => props.session.photos.find(photo => photo.id === openedId.value) ?? null)
const open = computed({
  get: () => opened.value !== null,
  set: (value: boolean) => {
    if (!value) openedId.value = null
  },
})
watch(openedId, () => {
  removal.clear()
})

const weight = computed(() => {
  const weight = props.session.weight
  if (!weight) return 'pas de pesée ce jour-là ni avant'
  return weight.day === props.session.day ? formatKg(weight.weightKg) : `${formatKg(weight.weightKg)} (pesée du ${formatFullDay(weight.day)})`
})
const measurements = computed(() => {
  const measurements = props.session.measurements
  if (!measurements?.values.length) return null
  const values = measurements.values.map(value => `${measureName(value)} ${formatCm(value.cm)}`).join(', ')
  return measurements.day === props.session.day ? values : `${values} (mesurées le ${formatFullDay(measurements.day)})`
})
const alt = (photo: ProgressPhoto) => `${poseLabel(photo.pose)}, ${formatFullDay(photo.day)}`

async function remove(photo: ProgressPhoto): Promise<void> {
  if (await removal.run(() => api.delete('/api/progression/photos/{id}', { path: { id: photo.id } }))) {
    openedId.value = null
    emit('removed', photo)
  }
}
</script>

<template>
  <UiCard :title="formatFullDay(session.day)">
    <p class="text-sm text-muted">
      Poids&nbsp;: {{ weight }}
    </p>
    <p
      v-if="measurements"
      class="text-sm text-muted"
    >
      Mensurations&nbsp;: {{ measurements }}
    </p>
    <ul class="m-0 grid list-none grid-cols-3 gap-2 p-0">
      <li
        v-for="photo in session.photos"
        :key="photo.id"
      >
        <button
          type="button"
          class="block w-full cursor-pointer overflow-hidden rounded-md border border-line bg-surface-raised p-0"
          data-photo
          :aria-label="`${poseLabel(photo.pose)}, ${formatFullDay(photo.day)}`"
          @click="openedId = photo.id"
        >
          <!-- The button says what it opens: the picture itself adds nothing to read. -->
          <img
            :src="photo.thumbnailUrl"
            alt=""
            :width="photo.width"
            :height="photo.height"
            loading="lazy"
            class="aspect-3/4 h-auto w-full object-cover"
          >
          <span class="block py-1 text-center text-sm">{{ poseLabel(photo.pose) }}</span>
        </button>
      </li>
    </ul>
    <UiDrawer
      v-model:open="open"
      :title="opened ? alt(opened) : ''"
    >
      <div
        v-if="opened"
        class="flex flex-col gap-4"
      >
        <UiAlert
          v-if="removal.message.value"
          tone="error"
        >
          {{ removal.message.value }}
        </UiAlert>
        <img
          :src="opened.url"
          :alt="alt(opened)"
          :width="opened.width"
          :height="opened.height"
          class="h-auto max-h-[70dvh] w-full rounded-md object-contain"
        >
        <div class="flex flex-wrap gap-2">
          <UiLinkButton
            :to="opened.downloadUrl"
            variant="secondary"
            external
            download
          >
            Télécharger
          </UiLinkButton>
          <UiConfirmButton
            label="Supprimer la photo"
            confirm-label="Confirmer (elle sera effacée)"
            :loading="removal.pending.value"
            @confirm="remove(opened)"
          />
        </div>
      </div>
    </UiDrawer>
  </UiCard>
</template>
