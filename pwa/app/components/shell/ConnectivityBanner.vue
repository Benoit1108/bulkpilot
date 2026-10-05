<script setup lang="ts">
import { useServiceHealth } from '~/features/system/health'

const { serviceStatus, apiError, refresh } = useServiceHealth()
const visible = computed(() => serviceStatus.value === 'unreachable' || serviceStatus.value === 'degraded')

/** While the service is out of reach (an update restarting it, often), it is checked again regularly. */
const RECHECK_MS = 20_000
const recovered = ref(false)

let timer: ReturnType<typeof setInterval> | undefined
const stopChecking = (): void => {
  clearInterval(timer)
  timer = undefined
}
const onVisible = (): void => {
  if (document.visibilityState === 'visible' && visible.value) void refresh()
}

useEventListener('online', () => refresh())
onMounted(() => document.addEventListener('visibilitychange', onVisible))
onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisible)
  stopChecking()
})
let wasDown = false
watch(serviceStatus, (status) => {
  if (status === 'unreachable' || status === 'degraded') {
    wasDown = true
    recovered.value = false
    timer ??= setInterval(() => void refresh(), RECHECK_MS)
  }
  else if (status === 'ok') {
    stopChecking()
    // Said once, in the same live region: the banner that was there is gone for a reason.
    if (wasDown) recovered.value = true
    wasDown = false
  }
})
</script>

<template>
  <!-- Always mounted so screen readers announce content changes. -->
  <div
    :class="{ 'flex items-center justify-between gap-3 rounded-md border border-danger bg-surface-raised px-4 py-3 text-sm': visible }"
    role="status"
  >
    <template v-if="!visible && recovered">
      <p class="sr-only">
        Service rétabli.
      </p>
    </template>
    <template v-if="visible">
      <p>{{ apiError?.userMessage ?? 'Le service rencontre un problème.' }}</p>
      <UiButton
        variant="secondary"
        @click="refresh()"
      >
        Réessayer
      </UiButton>
    </template>
  </div>
</template>
