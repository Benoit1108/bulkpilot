<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'
import { buttonClass } from '~/components/ui/classes'
import { CALLS_TO_ACTION, LEVELS, type Decision, type Recommendation } from '../coach'
import { proposalMacros, proposalSummary } from '../format'

/**
 * One recommendation (spec §68–69, §132): what the Coach found, why, and the user's answer.
 * Nothing changes until the user accepts; only a calorie proposal changes a value. A piece of
 * advice leads to where to act on it (spec §130).
 */
const props = defineProps<{
  recommendation: Recommendation
  /** Another answer is being recorded: this one waits (the page applies them one at a time). */
  busy?: boolean
  /** Why the answer to this one failed. */
  message?: string | null
}>()
defineEmits<{ decide: [decision: Decision] }>()

const titleId = useId()
const level = computed(() => LEVELS[props.recommendation.level])
const callToAction = computed(() => CALLS_TO_ACTION[props.recommendation.kind] ?? null)
const actions = computed<ReadonlyArray<{ decision: Decision, label: string, variant: 'primary' | 'secondary' | 'ghost' }>>(() => (props.recommendation.proposal
  ? [{ decision: 'accept', label: 'Accepter', variant: 'primary' }, { decision: 'reject', label: 'Refuser', variant: 'secondary' }, { decision: 'postpone', label: 'Plus tard', variant: 'ghost' }]
  : [{ decision: 'accept', label: 'C’est noté', variant: callToAction.value ? 'secondary' : 'primary' }, { decision: 'postpone', label: 'Plus tard', variant: 'ghost' }]))

const title = ref<HTMLElement | null>(null)
defineExpose({ focus: () => title.value?.focus() })
</script>

<template>
  <article
    class="flex flex-col gap-3 rounded-lg border border-l-4 border-line bg-surface p-4"
    :class="level.border"
    :aria-labelledby="titleId"
    data-recommendation
  >
    <h3
      :id="titleId"
      ref="title"
      class="flex flex-col gap-1"
      tabindex="-1"
    >
      <span class="text-sm font-semibold"><span aria-hidden="true">{{ level.emoji }}</span> {{ level.label }}<span class="sr-only">&nbsp;:</span></span>
      <span class="text-lg font-bold">{{ recommendation.title }}</span>
    </h3>
    <p>{{ recommendation.body }}</p>
    <template v-if="recommendation.proposal">
      <p class="font-semibold tabular-nums">
        {{ proposalSummary(recommendation.proposal) }}, à partir de demain
      </p>
      <p class="text-sm text-muted">
        {{ proposalMacros(recommendation.proposal) }}
      </p>
    </template>
    <details
      v-if="recommendation.why.length"
      class="group text-sm"
    >
      <summary class="inline-flex min-h-tap cursor-pointer list-none items-center gap-1 font-semibold text-accent-ink [&::-webkit-details-marker]:hidden">
        <ChevronRight
          class="size-4 transition-transform group-open:rotate-90 motion-reduce:transition-none"
          aria-hidden="true"
        />
        Pourquoi&nbsp;?
      </summary>
      <ul class="m-0 flex list-disc flex-col gap-1 ps-5">
        <li
          v-for="line in recommendation.why"
          :key="line"
        >
          {{ line }}
        </li>
      </ul>
    </details>
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <div class="flex flex-wrap gap-2">
      <NuxtLink
        v-if="callToAction && !recommendation.proposal"
        :to="callToAction.to"
        :class="buttonClass('primary')"
      >
        {{ callToAction.label }}
      </NuxtLink>
      <UiButton
        v-for="action in actions"
        :key="action.decision"
        :variant="action.variant"
        :loading="busy"
        @click="$emit('decide', action.decision)"
      >
        {{ action.label }}<span class="sr-only">&nbsp;: {{ recommendation.title }}</span>
      </UiButton>
    </div>
  </article>
</template>
