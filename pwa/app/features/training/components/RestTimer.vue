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
    class="rest"
    :aria-labelledby="titleId"
  >
    <div class="rest__line">
      <h2
        :id="titleId"
        ref="heading"
        class="rest__title"
        tabindex="-1"
      >
        Repos<template v-if="paused">
          en pause
        </template>
      </h2>
      <p class="rest__clock">
        <span aria-hidden="true">{{ formatClock(remaining) }}</span>
        <span class="visually-hidden">{{ spokenDuration(remaining) }} restantes</span>
      </p>
    </div>
    <p
      class="visually-hidden"
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
    <div class="rest__actions">
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

<style scoped>
.rest {
  position: sticky;
  top: calc(env(safe-area-inset-top) + var(--space-2));
  z-index: 5;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-accent);
  border-radius: var(--radius-md);
}

.rest__line {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
}

.rest__title {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.rest__title:focus {
  outline: none;
}

.rest__clock {
  font-size: clamp(2rem, 11vw, 3rem);
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.rest__actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.rest__actions > * {
  flex: 1 1 4.5rem;
}
</style>
