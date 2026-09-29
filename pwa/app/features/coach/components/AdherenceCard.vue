<script setup lang="ts">
import type { Adherence } from '../coach'
import { adherencePartText } from '../format'

/**
 * How well the plan was kept (spec §58): the score, and each part explained. A part with nothing to
 * expect (no target, no program) is not there at all; with nothing recorded yet, there is no score.
 */
defineProps<{ adherence: Adherence | null, span?: string }>()
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
    class="flex flex-col gap-3"
  >
    <p class="flex items-baseline gap-2">
      <span class="text-2xl font-bold tabular-nums">{{ adherence.score }}</span>
      <span class="text-muted">sur 100</span>
    </p>
    <UiProgressBar
      label="Adhérence"
      :value="adherence.score"
      :max="100"
      :value-text="`${adherence.score} sur 100`"
    />
    <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
      <li
        v-for="part in adherence.parts"
        :key="part.part"
      >
        {{ adherencePartText(part) }}
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
