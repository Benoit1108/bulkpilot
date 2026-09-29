<script setup lang="ts">
import type { ChartMark } from './chart'

/**
 * Horizontal marks of a chart (a target…), inside its SVG. They never stretch the scale: a mark
 * beyond the drawn range is pinned to the top or bottom edge, with an arrow pointing its way.
 */
const props = defineProps<{
  marks: ChartMark[]
  range: [number, number]
  y: (value: number) => number
  left: number
  right: number
  top: number
  bottom: number
}>()

const placed = computed(() => props.marks.map((mark) => {
  const off = mark.y > props.range[1] ? 'above' : mark.y < props.range[0] ? 'below' : null
  const at = off === 'above' ? props.top + 10 : off === 'below' ? props.bottom - 4 : props.y(mark.y)
  const arrow = off === 'above' ? `M${props.right - 4},${at - 9} l-4,6 h8 z` : `M${props.right - 4},${at + 2} l-4,-6 h8 z`
  return { ...mark, off, at, arrow }
}))
</script>

<template>
  <g
    v-for="mark in placed"
    :key="mark.label"
  >
    <line
      v-if="!mark.off"
      :x1="left"
      :x2="right"
      :y1="mark.at"
      :y2="mark.at"
      class="stroke-success"
      stroke-dasharray="2 4"
    />
    <path
      v-else
      :d="mark.arrow"
      class="fill-success"
    />
    <text
      :x="right - (mark.off ? 12 : 0)"
      :y="mark.off ? mark.at : mark.at - 4"
      text-anchor="end"
      class="fill-success stroke-surface text-xs"
      stroke-width="3"
      paint-order="stroke"
    >
      {{ mark.label }}
    </text>
  </g>
</template>
