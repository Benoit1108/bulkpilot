<script setup lang="ts">
import type { Component } from 'vue'
import { SlidersHorizontal } from 'lucide-vue-next'
import EmailStatus from '~/features/identity/components/EmailStatus.vue'
import CoachWidget from '~/features/dashboard/components/CoachWidget.vue'
import DashboardCustomizer from '~/features/dashboard/components/DashboardCustomizer.vue'
import GettingStarted from '~/features/dashboard/components/GettingStarted.vue'
import GoalWidget from '~/features/dashboard/components/GoalWidget.vue'
import MealsWidget from '~/features/dashboard/components/MealsWidget.vue'
import NutritionWidget from '~/features/dashboard/components/NutritionWidget.vue'
import RecordsWidget from '~/features/dashboard/components/RecordsWidget.vue'
import ReportWidget from '~/features/dashboard/components/ReportWidget.vue'
import WeightWidget from '~/features/dashboard/components/WeightWidget.vue'
import WorkoutWidget from '~/features/dashboard/components/WorkoutWidget.vue'
import { isKnownWidget, VIEWS, type DashboardView, type WidgetKey } from '~/features/dashboard/dashboard'
import { SHOWN_WIDGETS } from '~/features/dashboard/data'
import { useDashboardLayout } from '~/features/dashboard/layout'
import { formatLongDay } from '~/services/calendar/days'

definePageMeta({ title: 'Accueil' })

const { account } = useAccount()
const today = useToday()
// The page greets the user; the document title stays « Accueil ».
usePageHeading(() => (account.value ? `Bonjour ${account.value.firstName}` : null))

const view = ref<DashboardView>('day')
const { layout, error, refresh, save } = useDashboardLayout()

const WIDGETS: Record<WidgetKey, Component> = {
  weight: WeightWidget,
  nutrition: NutritionWidget,
  meals: MealsWidget,
  workout: WorkoutWidget,
  coach: CoachWidget,
  goal: GoalWidget,
  records: RecordsWidget,
  report: ReportWidget,
}
/** The day's meals mean nothing over a month; the weekly report, nothing for a single day. */
const HIDDEN_IN: Partial<Record<WidgetKey, DashboardView[]>> = { meals: ['week', 'month'], report: ['day'] }
// A widget of a newer server this app does not know is kept in the layout, not shown.
const shown = computed(() => (layout.value?.widgets ?? []).filter(widget => isKnownWidget(widget) && widget.visible && !HIDDEN_IN[widget.key]?.includes(view.value)))
provide(SHOWN_WIDGETS, computed(() => shown.value.map(widget => widget.key)))

/** A first visit: nothing set up yet, « Pour commencer » says what to do instead of eight empty widgets. */
const steps = ref<{ left: number, total: number } | null>(null)
const firstVisit = computed(() => steps.value !== null && steps.value.left === steps.value.total)

const customizing = ref(false)
const notice = ref<string | null>(null)
const customizeButton = ref<{ $el: HTMLElement } | null>(null)
async function customized(saved: boolean): Promise<void> {
  customizing.value = false
  if (saved) notice.value = 'Tableau de bord enregistré.'
  await nextTick()
  customizeButton.value?.$el.focus()
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- "Lundi 28 septembre": only the first letter is capitalised in French. -->
    <p class="-mt-3 text-muted first-letter:uppercase">
      {{ formatLongDay(today) }}
    </p>

    <EmailStatus
      v-if="account && !account.emailVerified"
      :account="account"
      compact
    />

    <div class="flex items-center justify-between gap-2">
      <UiPillGroup
        v-model="view"
        legend="Période du tableau de bord"
        name="dashboard-view"
        :options="VIEWS"
        hide-legend
      />
      <UiButton
        v-if="layout && !customizing"
        ref="customizeButton"
        variant="ghost"
        aria-label="Personnaliser le tableau de bord"
        title="Personnaliser"
        @click="customizing = true"
      >
        <SlidersHorizontal
          :size="20"
          aria-hidden="true"
        />
      </UiButton>
    </div>

    <UiLoadError
      v-if="error && !layout"
      @retry="refresh()"
    />
    <DashboardCustomizer
      v-else-if="customizing && layout"
      :widgets="layout.widgets"
      :save="save"
      @done="customized"
    />
    <template v-else-if="layout">
      <GettingStarted
        :today="today"
        @steps="(left, total) => steps = { left, total }"
      />
      <p
        v-if="firstVisit"
        class="sr-only"
      >
        Les widgets apparaîtront une fois ces premiers réglages faits.
      </p>
      <p
        v-else-if="!shown.length"
        class="text-muted"
      >
        Aucun widget affiché pour cette période&nbsp;: « Personnaliser » permet d’en montrer.
      </p>
      <div
        v-if="!firstVisit"
        class="grid gap-4 md:grid-cols-2"
      >
        <component
          :is="WIDGETS[widget.key]"
          v-for="widget in shown"
          :key="widget.key"
          :view="view"
          :today="today"
        />
      </div>
    </template>
    <p
      v-else
      class="text-sm text-muted"
      role="status"
    >
      Chargement…
    </p>
    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />
  </div>
</template>
