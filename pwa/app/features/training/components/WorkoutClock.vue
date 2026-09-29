<script setup lang="ts">
import { LONG_WORKOUT_MS } from '~/features/training/session'
import { formatClock, spokenDuration } from '~/features/training/workout'

/** The workout's chronometer (spec §44), with its own clock: the page does not re-render every second. */
const props = defineProps<{ startedAt: string }>()
const emit = defineEmits<{ long: [] }>()

const now = useNow(1000)
const elapsed = computed(() => now.value - new Date(props.startedAt).getTime())
const long = computed(() => elapsed.value >= LONG_WORKOUT_MS)
watch(long, value => value && emit('long'), { immediate: true })
</script>

<template>
  <span
    class="tabular-nums"
    :class="{ 'font-bold text-accent-ink': long }"
  >
    <span class="sr-only">Durée de la séance&nbsp;: {{ spokenDuration(elapsed) }}</span>
    <span aria-hidden="true">{{ formatClock(elapsed) }}</span><template v-if="long">
      · plus d’1&nbsp;h
    </template>
  </span>
</template>
