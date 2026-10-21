<script setup lang="ts">
import type { Adherence, AdherencePart } from '../coach'
import { adherencePartText } from '../format'

/**
 * How well the plan was kept (spec §58): the score as a ring, and each part with a dot per day or
 * session expected, filled when it was kept. A part with nothing to expect (no target, no program)
 * is not there at all; with nothing recorded yet, there is no score. The ring and the dots are
 * decorative: the text says the figures.
 */
const props = defineProps<{ adherence: Adherence | null, span?: string }>()

const dots = (part: AdherencePart) => Array.from({ length: part.expected }, (_, i) => i < part.done)
const ring = computed(() => [{ value: props.adherence?.score ?? 0, max: 100, tone: 'accent' as const }])
</script>

<template>
  <p
    v-if="!adherence || adherence.score === null"
    class="text-muted"
  >
    Ton adhérence s’affichera après quelques jours de saisie.
  </p>
  <div
    v-else
    class="flex flex-col gap-4"
  >
    <div class="flex items-center gap-4">
      <UiRing
        class="size-20"
        :rings="ring"
      />
      <p class="flex flex-col">
        <span
          class="text-figure leading-none font-bold tabular-nums"
          data-testid="adherence-score"
        >{{ adherence.score }}</span>
        <span class="text-sm text-muted">sur 100</span>
      </p>
    </div>
    <ul
      class="m-0 flex flex-col gap-3 p-0 text-sm"
      role="list"
    >
      <li
        v-for="part in adherence.parts"
        :key="part.part"
        class="flex flex-col gap-1"
      >
        <span>{{ adherencePartText(part) }}</span>
        <span
          class="flex flex-wrap gap-1"
          aria-hidden="true"
        >
          <span
            v-for="(kept, i) in dots(part)"
            :key="i"
            class="size-2.5 rounded-full"
            :class="kept ? 'bg-accent' : 'bg-surface-raised ring-1 ring-line ring-inset'"
          />
        </span>
      </li>
    </ul>
    <p
      v-if="span"
      class="text-sm text-muted"
    >
      {{ span }}
    </p>
  </div>
</template>
