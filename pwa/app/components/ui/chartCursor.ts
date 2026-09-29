import type { Ref } from 'vue'
import { nearest } from './chart'

/**
 * The day a chart shows the values of (ADR-0026): under the mouse, where a finger touched, or
 * picked with the arrow keys (then announced). Escape or leaving the chart closes the reading.
 */
export function useChartCursor(xs: Readonly<Ref<number[]>>, width: Readonly<Ref<number>>, read: (index: number) => string) {
  const active = ref<number | null>(null)
  const reading = computed(() => (active.value === null ? '' : read(active.value)))
  const announced = ref('')

  function pointAt(event: PointerEvent): void {
    const bounds = (event.currentTarget as Element).getBoundingClientRect()
    if (!xs.value.length || !bounds.width) return
    active.value = nearest(xs.value, ((event.clientX - bounds.left) / bounds.width) * width.value)
  }

  /** A finger lifted keeps the reading up (a tap shows it); a mouse leaving the chart hides it. */
  function leave(event: PointerEvent): void {
    if (event.pointerType === 'mouse') active.value = null
  }

  function close(): void {
    active.value = null
    announced.value = ''
  }

  function step(event: KeyboardEvent): void {
    if (event.key === 'Escape' && active.value !== null) {
      event.preventDefault()
      close()
      return
    }
    const last = xs.value.length - 1
    const moves: Record<string, number> = { ArrowLeft: (active.value ?? last + 1) - 1, ArrowRight: (active.value ?? -1) + 1, Home: 0, End: last }
    const next = moves[event.key]
    if (next === undefined || last < 0) return
    event.preventDefault()
    active.value = Math.min(last, Math.max(0, next))
    announced.value = reading.value
  }

  /** The reading follows the day and stays inside the chart: 30 % across, it shifts by 30 % of its own width. */
  const tooltip = computed(() => {
    const across = active.value === null ? 0 : Math.min(100, Math.max(0, ((xs.value[active.value] ?? 0) / width.value) * 100))
    return { left: `${across}%`, transform: `translateX(-${across}%)` }
  })

  return { active, reading, announced, tooltip, pointAt, leave, close, step }
}
