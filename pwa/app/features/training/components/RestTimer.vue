<script setup lang="ts">
import { Pause, Play } from 'lucide-vue-next'
import type { LocalWorkout } from '~/features/training/session'
import { formatClock, spokenDuration } from '~/features/training/workout'

/**
 * The rest after a set (spec §44): starts on its own, can be paused, changed or skipped. Driven
 * by an end time, so it stays right after the app was in the background; a compact bar, so the
 * set form stays in reach.
 */
const props = defineProps<{ rest: NonNullable<LocalWorkout['rest']> }>()
const emit = defineEmits<{ pause: [], resume: [], adjust: [seconds: number], skip: [], done: [] }>()

const now = useNow(250)
const remaining = computed(() => (props.rest.endsAt !== null ? Math.max(0, props.rest.endsAt - now.value) : props.rest.remainingMs ?? 0))
const paused = computed(() => props.rest.endsAt === null)
const progress = computed(() => (props.rest.totalMs > 0 ? 1 - remaining.value / props.rest.totalMs : 1))
const titleId = useId()
const heading = ref<HTMLElement | null>(null)
defineExpose({ focus: () => heading.value?.focus() })

/** Announced at a few steps only, not every second. */
const STEPS = [60, 30, 10]
const announcement = ref('')
watch(() => Math.ceil(remaining.value / 1000), (seconds, before) => {
  if (!paused.value && before !== undefined && STEPS.includes(seconds) && seconds < before) announcement.value = `Repos\u00A0: encore ${spokenDuration(seconds * 1000)}.`
})

// Also when it was already over (the app was closed during the rest).
watch(remaining, (value) => {
  if (value === 0 && !paused.value) {
    navigator.vibrate?.([200, 100, 200])
    emit('done')
  }
}, { immediate: true })
</script>

<template>
  <section
    class="sticky top-safe-2 z-5 flex flex-col gap-2 rounded-md border border-accent bg-surface-raised p-3"
    :aria-labelledby="titleId"
  >
    <div class="flex items-baseline justify-between gap-3">
      <h2
        :id="titleId"
        ref="heading"
        class="text-sm font-bold tracking-caps text-muted uppercase focus:outline-none"
        tabindex="-1"
      >
        Repos<template v-if="paused">
          en pause
        </template>
      </h2>
      <p class="text-clock leading-none font-extrabold tabular-nums">
        <span aria-hidden="true">{{ formatClock(remaining) }}</span>
        <span class="sr-only">{{ spokenDuration(remaining) }} restantes</span>
      </p>
    </div>
    <p
      class="sr-only"
      role="status"
    >
      {{ announcement }}
    </p>
    <UiProgressBar
      label="Repos écoulé"
      :value="Math.round(progress * 100)"
      :max="100"
      :value-text="`${spokenDuration(remaining)} restantes`"
    />
    <div class="flex flex-wrap gap-2 *:flex-[1_1_4.5rem]">
      <UiButton
        variant="secondary"
        @click="emit('adjust', -15)"
      >
        −15&nbsp;s
      </UiButton>
      <UiButton
        variant="secondary"
        :aria-pressed="paused"
        @click="paused ? emit('resume') : emit('pause')"
      >
        <component
          :is="paused ? Play : Pause"
          :size="18"
          aria-hidden="true"
        />
        Pause
      </UiButton>
      <UiButton
        variant="secondary"
        @click="emit('adjust', 15)"
      >
        +15&nbsp;s
      </UiButton>
      <UiButton @click="emit('skip')">
        Passer
      </UiButton>
    </div>
  </section>
</template>
