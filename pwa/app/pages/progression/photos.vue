<script setup lang="ts">
import { Camera } from 'lucide-vue-next'
import PhotoCompare from '~/features/progression/components/PhotoCompare.vue'
import PhotoSessionCard from '~/features/progression/components/PhotoSessionCard.vue'
import PhotoUpload from '~/features/progression/components/PhotoUpload.vue'
import { PROGRESSION_KEYS, type ProgressPhoto } from '~/features/progression/progression'

definePageMeta({ title: 'Photos' })

const api = useApi()
const { data: sessions, error, refresh } = useAsyncData(PROGRESSION_KEYS.photoSessions, () => api.get('/api/progression/photo-sessions'))

// Links expire after a few minutes (ADR-0027): new ones are asked for a little before. A failed
// renewal is tried again, later each time; back on the tab (timers sleep in the background), the
// links are looked at again. (A timer holds at most about 24 days: a later expiry waits that long.)
const LONGEST_TIMER = 2_147_483_647
const AHEAD = 30_000
let renewal: ReturnType<typeof setTimeout> | undefined
let failures = 0

function expiresIn(): number | null {
  const expiries = (sessions.value ?? []).flatMap(session => session.photos.map(photo => Date.parse(photo.expiresAt)))
  return expiries.length ? Math.min(...expiries) - Date.now() : null
}
function renewIn(delay: number): void {
  clearTimeout(renewal)
  renewal = setTimeout(renew, Math.min(LONGEST_TIMER, Math.max(10_000, delay)))
}
async function renew(): Promise<void> {
  clearTimeout(renewal)
  await refresh()
  if (!error.value) return
  failures++
  renewIn(Math.min(5 * 60_000, 10_000 * 2 ** failures))
}
watch(sessions, () => {
  failures = 0
  const left = expiresIn()
  if (left === null) clearTimeout(renewal)
  else renewIn(left - AHEAD)
}, { immediate: true })

function onVisible(): void {
  const left = expiresIn()
  if (document.visibilityState === 'visible' && left !== null && left < AHEAD) void renew()
}
onMounted(() => document.addEventListener('visibilitychange', onVisible))
onBeforeUnmount(() => {
  clearTimeout(renewal)
  document.removeEventListener('visibilitychange', onVisible)
})

// The photo's button is gone with it: focus goes to another photo of the day, or of another day.
const root = ref<HTMLElement | null>(null)
const status = ref<string | null>(null)
async function removed(photo: ProgressPhoto): Promise<void> {
  status.value = 'Photo supprimée.'
  await refresh()
  await nextTick()
  const next = root.value?.querySelector<HTMLElement>(`[data-session="${photo.day}"] [data-photo]`)
    ?? root.value?.querySelector<HTMLElement>('[data-photo]')
    ?? root.value?.querySelector<HTMLElement>('input[type="file"]')
  next?.focus()
}

// The global « + » leads here to add a photo: the file picker takes the focus.
const upload = ref<InstanceType<typeof PhotoUpload> | null>(null)
useAddRequest('photo', upload)
</script>

<template>
  <div
    ref="root"
    class="flex max-w-reading flex-col gap-4"
  >
    <!-- UiPage's reading layout, on an element of its own: the page needs a ref on its root. -->
    <ShellBackLink
      to="/progression"
      label="Progression"
    />
    <UiCard title="Ajouter une photo">
      <PhotoUpload
        ref="upload"
        @uploaded="refresh()"
      />
    </UiCard>
    <UiLoadError
      v-if="error && !sessions"
      @retry="refresh()"
    />
    <template v-else-if="sessions">
      <UiCard
        v-if="sessions.length > 1"
        title="Comparer"
      >
        <PhotoCompare :sessions="sessions" />
      </UiCard>
      <PhotoSessionCard
        v-for="session in sessions"
        :key="session.day"
        :session="session"
        :data-session="session.day"
        @removed="removed"
      />
      <UiCard v-if="!sessions.length">
        <UiEmptyState
          :icon="Camera"
          title="Aucune photo"
          description="Des photos de face, de profil et de dos, prises dans les mêmes conditions, se comparent ensuite côte à côte."
        />
      </UiCard>
    </template>
    <UiStatus
      :message="status"
      @dismiss="status = null"
    />
  </div>
</template>
