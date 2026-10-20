<script setup lang="ts">
import { illustrationOf } from '~/features/training/illustrations'
import type { Muscle } from '~/features/training/training'
import MuscleMap from './MuscleMap.vue'

/**
 * How an exercise is done: the drawing's two frames (start, effort) painted in the theme's colour
 * through a mask, or the single image some exercises have, or else the muscles it works. A
 * thumbnail (a list, a workout) shows the effort frame alone, as decoration beside the name.
 */
const props = withDefaults(defineProps<{
  libraryKey: string | null
  name: string
  primary: Muscle
  secondary: readonly Muscle[]
  thumbnail?: boolean
  /** Smaller frames, beside the sets of a workout. */
  compact?: boolean
}>(), { thumbnail: false, compact: false })

const illustration = computed(() => illustrationOf(props.libraryKey))
const mask = (src: string) => ({ maskImage: `url(${src})`, WebkitMaskImage: `url(${src})` })

// A mask cannot wait to be scrolled to as an image can: a thumbnail sets it once it is near.
const box = ref<HTMLElement | null>(null)
const near = ref(!props.thumbnail)
let watcher: IntersectionObserver | undefined
onMounted(() => {
  if (near.value || !box.value) return
  if (typeof IntersectionObserver === 'undefined') {
    near.value = true
    return
  }
  watcher = new IntersectionObserver((entries) => {
    if (entries.some(entry => entry.isIntersecting)) {
      near.value = true
      watcher?.disconnect()
    }
  }, { rootMargin: '200px' })
  watcher.observe(box.value)
})
onBeforeUnmount(() => watcher?.disconnect())
</script>

<template>
  <div
    v-if="thumbnail"
    ref="box"
    class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-md bg-canvas"
    aria-hidden="true"
  >
    <span
      v-if="illustration?.kind === 'drawing'"
      class="size-12 bg-ink mask-contain mask-center mask-no-repeat"
      :style="near ? mask(illustration.end) : undefined"
    />
    <img
      v-else-if="illustration?.kind === 'image'"
      :src="illustration.src"
      alt=""
      loading="lazy"
      class="size-full object-cover"
    >
    <MuscleMap
      v-else
      :primary="primary"
      :secondary="secondary"
      one-side
      class="h-12"
    />
  </div>
  <figure
    v-else
    class="flex flex-col gap-2"
  >
    <div
      v-if="illustration?.kind === 'drawing'"
      class="grid grid-cols-2 gap-3"
    >
      <div
        v-for="frame in [{ src: illustration.start, label: 'Départ' }, { src: illustration.end, label: 'Effort' }]"
        :key="frame.label"
        class="flex flex-col items-center gap-1"
      >
        <span
          role="img"
          :aria-label="`${name}\u00A0: ${frame.label.toLowerCase()}`"
          class="aspect-square w-full bg-ink mask-contain mask-center mask-no-repeat"
          :class="compact ? 'max-w-32' : 'max-w-56'"
          :style="mask(frame.src)"
        />
        <span
          class="text-xs text-muted"
          aria-hidden="true"
        >{{ frame.label }}</span>
      </div>
    </div>
    <img
      v-else-if="illustration?.kind === 'image'"
      :src="illustration.src"
      :alt="name"
      loading="lazy"
      class="w-full rounded-md object-contain"
      :class="compact ? 'max-h-32' : 'max-h-64'"
    >
    <MuscleMap
      v-else
      :primary="primary"
      :secondary="secondary"
      class="mx-auto"
      :class="compact ? 'max-h-32' : 'max-h-64'"
    />
    <figcaption
      v-if="illustration"
      class="text-xs text-muted"
    >
      {{ illustration.credit }} · <NuxtLink
        to="/reglages/credits"
        class="text-accent-ink"
      >crédits</NuxtLink>
    </figcaption>
  </figure>
</template>
