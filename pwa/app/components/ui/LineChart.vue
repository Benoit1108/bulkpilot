<script setup lang="ts">
import ChartLegend from './ChartLegend.vue'
import ChartMarks from './ChartMarks.vue'
import { useChartCursor } from './chartCursor'
import { DASHES, dayNumber, dayTicks, FILLS, linePath, niceTicks, scale, STROKES, textWidth, tickCount, type ChartMark as Mark, type ChartSeries as Series } from './chart'

/**
 * A chart of values over days (ADR-0026): SVG in the theme's colours, a title and a one-sentence
 * summary, the values under the pointer, the finger or the arrow keys (announced), and the table
 * of values. Series tell themselves apart by their line (solid, dashed, dots), not only by colour.
 * Marks (a target…) never stretch the scale: one off the chart is pinned to its edge, arrowed.
 */
export type ChartSeries = Series
export type ChartMark = Mark

const props = withDefaults(defineProps<{
  title: string
  summary: string
  series: ChartSeries[]
  marks?: ChartMark[]
  formatY: (value: number) => string
  formatX: (day: string) => string
  height?: number
  /** The first and last days of the axis: two charts given the same line up day for day. */
  xDomain?: [string, string]
}>(), { marks: () => [], height: 220, xDomain: undefined })

const box = ref<HTMLElement | null>(null)
const width = ref(640)
let observer: ResizeObserver | undefined
onMounted(() => {
  if (!box.value || typeof ResizeObserver === 'undefined') return
  observer = new ResizeObserver(([entry]) => {
    if (entry) width.value = Math.max(240, Math.round(entry.contentRect.width))
  })
  observer.observe(box.value)
})
onBeforeUnmount(() => observer?.disconnect())

const days = computed(() => [...new Set(props.series.flatMap(line => line.points.map(point => point.x)))].sort())
const domain = computed(() => props.xDomain ?? (days.value.length ? [days.value[0]!, days.value.at(-1)!] as const : null))
const yTicks = computed(() => {
  const values = props.series.flatMap(line => line.points.map(point => point.y))
  return values.length ? niceTicks(Math.min(...values), Math.max(...values)) : []
})
/** Room on the left for the longest value label, as few pixels as it takes. */
const pad = computed(() => ({ top: 12, right: 12, bottom: 28, left: Math.max(28, ...yTicks.value.map(tick => textWidth(props.formatY(tick)))) + 10 }))
const bottom = computed(() => props.height - pad.value.bottom)
const x = computed(() => {
  const [first, last] = domain.value ? domain.value.map(dayNumber) : [0, 1]
  return scale(first!, last!, pad.value.left, width.value - pad.value.right)
})
const y = computed(() => scale(yTicks.value[0] ?? 0, yTicks.value.at(-1) ?? 1, bottom.value, pad.value.top))

/** Everything drawn is computed once per change of data or size, not on each pointer move. */
const drawn = computed(() => props.series.map((line) => {
  const places = line.points.map(point => ({ x: x.value(dayNumber(point.x)), y: y.value(point.y) }))
  return { ...line, tone: line.tone ?? 'accent', places, path: linePath(places), dots: line.variant === 'dots' || places.length === 1 }
}))
const xTicks = computed(() => {
  if (!domain.value) return []
  const [first, last] = domain.value
  const count = tickCount(width.value - pad.value.left - pad.value.right, textWidth(props.formatX(first)) + 8)
  const ticks = dayTicks(first, last, count)
  const anchor = (i: number) => (ticks.length === 1 ? 'middle' : i === 0 ? 'start' : i === ticks.length - 1 ? 'end' : 'middle')
  return ticks.map((day, i) => ({ day, at: x.value(dayNumber(day)), anchor: anchor(i) }))
})
const xs = computed(() => days.value.map(day => x.value(dayNumber(day))))
const byDay = computed(() => props.series.map(line => ({ name: line.name, values: new Map(line.points.map(point => [point.x, point.y])) })))

const { active, reading, announced, tooltip, pointAt, leave, close, step } = useChartCursor(xs, width, (index) => {
  const day = days.value[index]!
  const values = byDay.value.flatMap(line => (line.values.has(day) ? [`${line.name}\u00A0: ${props.formatY(line.values.get(day)!)}`] : []))
  return `${props.formatX(day)} — ${values.join(', ')}`
})
const activeDay = computed(() => (active.value === null ? null : days.value[active.value] ?? null))
const titleId = useId()
const summaryId = useId()
const helpId = useId()
</script>

<template>
  <figure class="m-0 flex flex-col gap-2">
    <figcaption class="flex flex-col gap-1">
      <span
        :id="titleId"
        class="font-semibold"
      >{{ title }}</span>
      <span
        :id="summaryId"
        class="text-sm text-muted"
      >{{ summary }}</span>
      <span
        :id="helpId"
        class="sr-only"
      >Flèches gauche et droite pour parcourir les jours, Échap pour fermer.</span>
    </figcaption>
    <div
      ref="box"
      class="relative"
    >
      <svg
        class="block w-full touch-pan-y rounded-md focus-visible:outline-offset-4"
        :viewBox="`0 0 ${width} ${height}`"
        :height="height"
        role="group"
        :aria-labelledby="titleId"
        :aria-describedby="`${summaryId} ${helpId}`"
        tabindex="0"
        @pointerdown="pointAt"
        @pointermove="pointAt"
        @pointerleave="leave"
        @keydown="step"
        @blur="close"
      >
        <g aria-hidden="true">
          <g
            v-for="tick in yTicks"
            :key="tick"
          >
            <line
              :x1="pad.left"
              :x2="width - pad.right"
              :y1="y(tick)"
              :y2="y(tick)"
              class="stroke-line"
            />
            <text
              :x="pad.left - 6"
              :y="y(tick) + 4"
              text-anchor="end"
              class="fill-muted text-xs"
            >{{ formatY(tick) }}</text>
          </g>
          <text
            v-for="tick in xTicks"
            :key="tick.day"
            :x="tick.at"
            :y="height - 8"
            :text-anchor="tick.anchor"
            class="fill-muted text-xs"
          >{{ formatX(tick.day) }}</text>
          <ChartMarks
            :marks="marks"
            :range="[yTicks[0] ?? -Infinity, yTicks.at(-1) ?? Infinity]"
            :y="y"
            :left="pad.left"
            :right="width - pad.right"
            :top="pad.top"
            :bottom="bottom"
          />
          <template
            v-for="line in drawn"
            :key="line.name"
          >
            <path
              v-if="!line.dots"
              :d="line.path"
              fill="none"
              stroke-width="2"
              stroke-linejoin="round"
              :stroke-dasharray="DASHES[line.variant]"
              :class="STROKES[line.tone]"
            />
            <template v-else>
              <circle
                v-for="(at, i) in line.places"
                :key="i"
                :cx="at.x"
                :cy="at.y"
                r="3"
                :class="FILLS[line.tone]"
              />
            </template>
          </template>
          <line
            v-if="activeDay"
            :x1="xs[active!]"
            :x2="xs[active!]"
            :y1="pad.top"
            :y2="bottom"
            class="stroke-ink"
            stroke-dasharray="3 3"
          />
        </g>
      </svg>
      <p
        v-if="activeDay"
        class="pointer-events-none absolute top-0 max-w-[60%] rounded-sm border border-line bg-surface-raised px-2 py-1 text-xs shadow-raised"
        :style="tooltip"
        aria-hidden="true"
      >
        {{ reading }}
      </p>
      <p
        class="sr-only"
        role="status"
      >
        {{ announced }}
      </p>
    </div>
    <ChartLegend :series="drawn" />
    <details class="text-sm">
      <summary class="min-h-tap cursor-pointer content-center font-semibold text-accent-ink">
        Voir les valeurs
      </summary>
      <slot name="table" />
    </details>
  </figure>
</template>
