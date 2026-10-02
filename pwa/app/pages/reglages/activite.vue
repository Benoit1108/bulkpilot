<script setup lang="ts">
import { History } from 'lucide-vue-next'
import { ACTIVITY_KEY, ACTIVITY_PAGE, activityDetails, activityLabel, asPaged, type Activity } from '~/features/activity/activity'
import { daysText, useDataPolicy } from '~/features/data/policy'
import { formatMoment } from '~/services/calendar/days'

definePageMeta({ title: 'Activité du compte' })

const api = useApi()
const { firstPage, items, error, refresh, hasMore, loading, notice, list, keep, loadMore } = usePagedList<Activity>(
  ACTIVITY_KEY,
  async cursor => (await api.get('/api/me/activity', { query: { limit: ACTIVITY_PAGE, ...cursor } })).map(asPaged),
  { size: ACTIVITY_PAGE, noun: ['action'] },
)
const { data: policy } = useDataPolicy()
// Each line worked out once, not on every render of the list.
const rows = computed(() => items.value.map(entry => ({ entry, label: activityLabel(entry), details: activityDetails(entry), at: formatMoment(entry.occurredAt) })))
</script>

<template>
  <div class="flex max-w-reading flex-col gap-4">
    <ShellBackLink
      to="/reglages"
      label="Réglages"
    />
    <p class="text-muted">
      Les actions importantes sur ton compte et tes objectifs, pour savoir quand et pourquoi une valeur a changé.
      <template v-if="policy?.activityRetentionDays">
        Elles sont gardées {{ daysText(policy.activityRetentionDays) }}.
      </template>
    </p>
    <UiLoadError
      v-if="error && !firstPage"
      @retry="refresh()"
    />
    <UiCard v-else-if="items.length">
      <UiAlert
        v-if="loading.message.value"
        tone="error"
      >
        {{ loading.message.value }}
      </UiAlert>
      <ul
        ref="list"
        class="m-0 flex list-none flex-col p-0 focus-visible:outline-2 focus-visible:outline-accent"
        role="list"
        aria-label="Activité, la plus récente d’abord"
        tabindex="-1"
      >
        <li
          v-for="{ entry, label, details, at } in rows"
          :key="entry.id"
          :ref="element => keep(entry.id, element)"
          class="flex flex-col gap-0.5 border-line py-3 not-first:border-t focus-visible:outline-2 focus-visible:outline-accent"
          tabindex="-1"
        >
          <p class="font-semibold">
            {{ label }}
          </p>
          <p
            v-if="details"
            class="text-sm"
          >
            {{ details }}
          </p>
          <p class="text-sm text-muted">
            <time :datetime="entry.occurredAt">{{ at }}</time>
          </p>
        </li>
      </ul>
      <UiButton
        v-if="hasMore"
        variant="secondary"
        :loading="loading.pending.value"
        @click="loadMore"
      >
        Voir plus
      </UiButton>
    </UiCard>
    <UiCard v-else-if="firstPage">
      <UiEmptyState
        :icon="History"
        title="Rien pour l’instant"
        description="Tes connexions, changements de mot de passe et d’objectifs apparaîtront ici."
      />
    </UiCard>
    <p
      v-else
      role="status"
      class="text-muted"
    >
      Chargement…
    </p>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
</template>
