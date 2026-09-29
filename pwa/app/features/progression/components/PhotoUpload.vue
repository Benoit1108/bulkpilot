<script setup lang="ts">
import { PILL } from '~/components/ui/classes'
import { localDay } from '~/services/calendar/days'
import { shrinkPhoto } from '~/services/media/shrink'
import { poseLabel, POSES, type Pose, type ProgressPhoto } from '../progression'

/**
 * Adds a progress photo (spec §60): taken now or picked from the phone, of a day and a pose.
 * It is shrunk here, then kept by the server re-encoded, without its metadata (ADR-0027).
 */
const emit = defineEmits<{ uploaded: [photo: ProgressPhoto] }>()

const api = useApi()
const { pending, message, run } = useSubmission()
const today = localDay()
const day = ref(today)
const pose = ref<Pose>('front')
const file = ref<File | null>(null)
const preview = ref<string | null>(null)
const saved = ref<string | null>(null)
const input = ref<HTMLInputElement | null>(null)
const inputId = useId()
const hintId = useId()

function picked(event: Event): void {
  const chosen = (event.target as HTMLInputElement).files?.[0] ?? null
  if (preview.value) URL.revokeObjectURL(preview.value)
  file.value = chosen
  preview.value = chosen ? URL.createObjectURL(chosen) : null
}
onBeforeUnmount(() => {
  if (preview.value) URL.revokeObjectURL(preview.value)
})

/** After a pose, the next one: face, then profile, then back, as they are usually taken. */
function nextPose(taken: Pose): Pose {
  const at = POSES.findIndex(option => option.value === taken)
  return POSES[(at + 1) % POSES.length]!.value
}

async function submit(): Promise<void> {
  const chosen = file.value
  if (!chosen || pending.value) return
  saved.value = null
  await run(async () => {
    const form = new FormData()
    const shrunk = await shrinkPhoto(chosen)
    // Sent as it was, the file keeps its name (and so its extension); redrawn, it is a JPEG.
    form.append('file', shrunk, shrunk === chosen ? chosen.name : 'photo.jpg')
    form.append('day', day.value)
    form.append('pose', pose.value)
    const photo = await api.upload('/api/progression/photos', form)
    saved.value = `Photo ajoutée (${poseLabel(photo.pose).toLowerCase()}).`
    file.value = null
    if (preview.value) URL.revokeObjectURL(preview.value)
    preview.value = null
    if (input.value) input.value.value = ''
    pose.value = nextPose(photo.pose)
    emit('uploaded', photo)
  })
  // The button is disabled again (no file): the next photo is picked from where the last one was.
  if (!file.value) {
    await nextTick()
    input.value?.focus()
  }
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
    <!-- Nothing changes while a photo is on its way: what is sent is what was chosen. -->
    <fieldset
      class="m-0 flex min-w-0 flex-col gap-4 border-0 p-0"
      :disabled="pending"
    >
      <div class="flex flex-col gap-1">
        <label
          :for="inputId"
          class="text-sm font-semibold"
        >Photo</label>
        <input
          :id="inputId"
          ref="input"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="min-h-tap text-sm file:mr-3 file:min-h-tap file:cursor-pointer file:rounded-md file:border file:border-line file:bg-surface-raised file:px-4 file:font-semibold file:text-ink"
          :aria-describedby="hintId"
          @change="picked"
        >
        <p
          :id="hintId"
          class="text-sm text-muted"
        >
          JPEG, PNG ou WebP, 15&nbsp;Mo au plus. Elle reste privée&nbsp;: ni la position ni l’appareil ne sont gardés.
        </p>
      </div>
      <img
        v-if="preview"
        :src="preview"
        alt="Aperçu de la photo choisie"
        class="max-h-64 w-auto self-start rounded-md border border-line object-contain"
      >
      <fieldset class="m-0 min-w-0 border-0 p-0">
        <legend class="mb-1 text-sm font-semibold">
          Pose
        </legend>
        <div class="flex flex-wrap gap-2">
          <label
            v-for="option in POSES"
            :key="option.value"
            :class="PILL"
          >
            <input
              v-model="pose"
              type="radio"
              name="photo-pose"
              :value="option.value"
              class="sr-only"
            >
            {{ option.label }}
          </label>
        </div>
      </fieldset>
      <UiInput
        v-model="day"
        label="Jour"
        type="date"
        required
        :max="today"
      />
    </fieldset>
    <div class="flex flex-wrap items-center gap-3">
      <UiButton
        type="submit"
        :loading="pending"
        :disabled="!file"
      >
        Ajouter la photo
      </UiButton>
      <UiStatus
        :message="saved"
        @dismiss="saved = null"
      />
    </div>
  </form>
</template>
