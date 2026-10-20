<script setup lang="ts">
import { BACK, FRONT, type BodyPart } from '~/features/training/bodyMap'
import { MUSCLES, labelOf, type Muscle } from '~/features/training/training'

/**
 * The body from the front and from the back, the muscles an exercise works in the accent colour:
 * full for the main one, lighter for the others (and their names said in words). The outlines are
 * react-body-highlighter's (MIT, see the credits page).
 */
const props = withDefaults(defineProps<{
  primary: Muscle
  secondary: readonly Muscle[]
  /** A small picture shows one side only: the one the main muscle is seen from. */
  oneSide?: boolean
}>(), { oneSide: false })

function fill(part: BodyPart): string {
  if (part.muscles.includes(props.primary)) return 'fill-accent'
  if (part.muscles.some(muscle => props.secondary.includes(muscle))) return 'fill-accent/45'
  return part.muscles.length ? 'fill-surface-raised' : 'fill-line'
}
/** Seen from behind: the side a small picture of these shows. */
const BACK_MUSCLES: readonly Muscle[] = ['lats', 'upper_back', 'traps', 'lower_back', 'rear_delts', 'triceps', 'glutes', 'hamstrings', 'calves']
const views = computed(() => {
  const sides = props.oneSide ? [{ parts: BACK_MUSCLES.includes(props.primary) ? BACK : FRONT, shift: 0 }] : [{ parts: FRONT, shift: 0 }, { parts: BACK, shift: 1100 }]
  return sides.map(view => ({ ...view, parts: view.parts.map(part => ({ points: part.points, fill: fill(part) })) }))
})
const label = computed(() => {
  const others = props.secondary.map(muscle => labelOf(MUSCLES, muscle).toLowerCase())
  return `${labelOf(MUSCLES, props.primary)} en principal${others.length ? `\u00A0; ${others.join(', ')} en secondaire` : ''}`
})
</script>

<template>
  <svg
    :viewBox="oneSide ? '0 0 1000 2200' : '0 0 2100 2200'"
    role="img"
    :aria-label="label"
  >
    <g
      v-for="view in views"
      :key="view.shift"
      :transform="`translate(${view.shift} 0)`"
    >
      <template
        v-for="(part, p) in view.parts"
        :key="p"
      >
        <polygon
          v-for="(points, i) in part.points"
          :key="i"
          :points="points"
          :class="part.fill"
        />
      </template>
    </g>
  </svg>
</template>
