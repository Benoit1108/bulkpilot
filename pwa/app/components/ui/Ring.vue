<script setup lang="ts">
/**
 * Concentric progress rings, outermost first, as Apple's activity rings: the day's calories, then
 * each macro. A ring is closed at its target and stays closed past it, in its own colour: going
 * over is never an alarm (docs/DESIGN.md), the text next to it says by how much. With `label`
 * it is an image named by its figures; without, decoration beside text that says them.
 */
export interface RingValue {
  value: number
  max: number
  tone: 'accent' | 'protein' | 'carbs' | 'fat'
}

const props = defineProps<{ rings: readonly RingValue[], label?: string }>()

const STROKES = { accent: 'stroke-accent', protein: 'stroke-protein', carbs: 'stroke-carbs', fat: 'stroke-fat' } as const
/** The outer ring is the thickest; each next one sits inside it with a little gap. */
const OUTER = 11
const INNER = 8
const GAP = 2

const drawn = computed(() => {
  let edge = 50
  return props.rings.map((ring, i) => {
    const width = i === 0 ? OUTER : INNER
    const radius = edge - width / 2
    edge -= width + GAP
    const length = 2 * Math.PI * radius
    const ratio = ring.max > 0 ? ring.value / ring.max : 0
    return {
      radius,
      width,
      length,
      shown: length * Math.min(1, Math.max(0, ratio)),
      stroke: STROKES[ring.tone],
    }
  })
})
</script>

<template>
  <svg
    viewBox="0 0 100 100"
    :role="label ? 'img' : undefined"
    :aria-label="label"
    :aria-hidden="label ? undefined : 'true'"
    class="shrink-0"
  >
    <!-- Starting at the top, as a clock. -->
    <g
      v-for="(ring, i) in drawn"
      :key="i"
      fill="none"
      :stroke-width="ring.width"
      transform="rotate(-90 50 50)"
    >
      <circle
        cx="50"
        cy="50"
        :r="ring.radius"
        class="stroke-line"
      />
      <circle
        v-if="ring.shown > 0"
        cx="50"
        cy="50"
        :r="ring.radius"
        stroke-linecap="round"
        :stroke-dasharray="`${ring.shown} ${ring.length}`"
        :class="ring.stroke"
      />
    </g>
  </svg>
</template>
