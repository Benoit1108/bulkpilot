<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import { HEADER_BUTTON, LIST_ROW } from '~/components/ui/classes'
import { fetchNextWorkout } from '~/features/dashboard/data'
import { QUICK_ACTIONS, type QuickAction } from '~/features/shell/quickActions'
import { useWorkoutSession } from '~/features/training/session'
import { useStartWorkout } from '~/features/training/startWorkout'

/**
 * The global « + » (spec §14): what to add, from any page. On phones it floats above the bottom
 * bar, within the thumb's reach and out of the page title's way; from md it sits in the header.
 */
const open = ref(false)
const api = useApi()
const session = useWorkoutSession()
const { start, pending: starting, message: startError } = useStartWorkout()

/** Leaving for the chosen form closes the panel (the browser gives the focus back to the button). */
const close = () => {
  open.value = false
}

/** The next day of the program followed, started; a workout in progress is resumed; without a program, its page. */
async function train(action: QuickAction): Promise<void> {
  await session.load()
  if (session.workout.value) {
    close()
    await navigateTo('/training/seance')
    return
  }
  const next = await fetchNextWorkout(api).catch(() => null)
  if (!next || next.inProgress) {
    close()
    await navigateTo(action.to)
    return
  }
  if (await start({ id: next.programId, currentVersion: next.versionNumber }, next.dayIndex)) close()
}
</script>

<template>
  <button
    type="button"
    :class="`${HEADER_BUTTON} max-md:fixed max-md:right-4 max-md:bottom-above-nav max-md:z-20 max-md:size-14 max-md:border-transparent max-md:bg-accent-fill max-md:text-accent-contrast max-md:shadow-raised`"
    aria-haspopup="dialog"
    aria-label="Ajouter"
    title="Ajouter"
    @click="open = true"
  >
    <Plus
      :size="24"
      aria-hidden="true"
    />
  </button>
  <UiDrawer
    v-model:open="open"
    title="Ajouter"
  >
    <UiAlert
      v-if="startError"
      tone="error"
    >
      {{ startError }}
    </UiAlert>
    <ul
      class="m-0 list-none p-0"
      role="list"
    >
      <li
        v-for="action in QUICK_ACTIONS"
        :key="action.label"
      >
        <button
          v-if="action.startsWorkout"
          type="button"
          :class="`${LIST_ROW} flex-row! items-center gap-3 text-ink`"
          :aria-busy="starting || undefined"
          :disabled="starting"
          @click="train(action)"
        >
          <component
            :is="action.icon"
            :size="20"
            aria-hidden="true"
          />
          {{ action.label }}
        </button>
        <NuxtLink
          v-else
          :to="action.to"
          :class="`${LIST_ROW} flex-row! items-center gap-3 no-underline`"
          @click="close"
        >
          <component
            :is="action.icon"
            :size="20"
            aria-hidden="true"
          />
          {{ action.label }}
        </NuxtLink>
      </li>
    </ul>
  </UiDrawer>
</template>
