<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import AddFoodDrawer from '~/features/nutrition/components/AddFoodDrawer.vue'
import AveragesCard from '~/features/nutrition/components/AveragesCard.vue'
import DaySummary from '~/features/nutrition/components/DaySummary.vue'
import EntryDrawer from '~/features/nutrition/components/EntryDrawer.vue'
import MealBuilderDrawer from '~/features/nutrition/components/MealBuilderDrawer.vue'
import MealSection from '~/features/nutrition/components/MealSection.vue'
import SaveFavoriteDrawer from '~/features/nutrition/components/SaveFavoriteDrawer.vue'
import { dayLabel, isDay, localDay, shiftDay } from '~/services/calendar/days'
import { slotAt, type Entry, type MealSlot } from '~/features/nutrition/nutrition'

definePageMeta({ title: 'Nutrition' })

const route = useRoute()
const today = ref(localDay())
// The day lives in the URL (?jour=YYYY-MM-DD): back/forward and reloads keep it.
const day = computed(() => (isDay(route.query.jour) ? route.query.jour : today.value))
const label = computed(() => dayLabel(day.value, today.value))
const isToday = computed(() => day.value >= today.value)

const { journal, previous, status, error, refresh, log, logMany, logFavorite, correct, remove } = useNutritionDay(day)
const shownDay = computed(() => journal.value ?? previous.value)

const dayLabelElement = ref<HTMLElement | null>(null)
async function goTo(target: string): Promise<void> {
  await navigateTo({ query: target === today.value ? {} : { jour: target } })
  // The button that was pressed may be gone ("Aujourd’hui") or inactive: keep focus on the day.
  dayLabelElement.value?.focus()
}
function next(): void {
  if (!isToday.value) void goTo(shiftDay(day.value, 1))
}

// An installed PWA can stay open past midnight.
function refreshToday(): void {
  if (document.visibilityState === 'visible') today.value = localDay()
}
onMounted(() => document.addEventListener('visibilitychange', refreshToday))
onBeforeUnmount(() => document.removeEventListener('visibilitychange', refreshToday))

/** Confirmation of the last action, shown and announced; focus then returns to that meal. */
const notice = ref<string | null>(null)
async function done(message: string, slot: MealSlot): Promise<void> {
  notice.value = message
  await nextTick()
  document.getElementById(`meal-title-${slot}`)?.focus()
}

const adding = ref(false)
const addingTo = ref<MealSlot>('breakfast')
const initialQuery = ref('')
function add(slot: MealSlot, query = ''): void {
  // A search asked from elsewhere (the palette) fills this opening only.
  initialQuery.value = query
  addingTo.value = slot
  adding.value = true
}

const editing = ref(false)
const edited = ref<Entry | null>(null)
function edit(entry: Entry): void {
  edited.value = entry
  editing.value = true
}

// The global « + » and the search open the adding panel (?ajouter=aliment[&recherche=…]), then leave the URL clean.
watch(() => route.query.ajouter, async (value) => {
  if (value !== 'aliment') return
  add(slotAt(), typeof route.query.recherche === 'string' ? route.query.recherche : '')
  const { ajouter: _added, recherche: _searched, ...rest } = route.query
  await navigateTo({ query: rest }, { replace: true })
}, { immediate: true })

const building = ref(false)

const savingFavorite = ref(false)
const favoriteSlot = ref<MealSlot>('breakfast')
function saveFavorite(slot: MealSlot): void {
  favoriteSlot.value = slot
  savingFavorite.value = true
}

const shortcuts = [
  { to: '/nutrition/objectifs', title: 'Objectifs', summary: 'Calories et macros, calcul détaillé' },
  { to: '/nutrition/repartition', title: 'Répartition des repas', summary: 'Part des calories par repas' },
  { to: '/nutrition/recettes', title: 'Mes recettes', summary: 'Plats maison, portions' },
  { to: '/nutrition/favoris', title: 'Repas favoris', summary: 'Repas habituels, ajoutés en un geste' },
  { to: '/nutrition/aliments', title: 'Mes aliments', summary: 'Produits et aliments personnels' },
] as const
</script>

<template>
  <div class="flex flex-col gap-4">
    <nav
      class="flex items-center gap-2"
      aria-label="Changer de jour"
    >
      <UiButton
        variant="ghost"
        aria-label="Jour précédent"
        @click="goTo(shiftDay(day, -1))"
      >
        <ChevronLeft
          :size="20"
          aria-hidden="true"
        />
      </UiButton>
      <p
        ref="dayLabelElement"
        class="flex-1 text-center font-bold first-letter:uppercase focus:outline-none"
        tabindex="-1"
        data-testid="day-label"
        aria-live="polite"
      >
        {{ label }}
      </p>
      <UiButton
        variant="ghost"
        aria-label="Jour suivant"
        :aria-disabled="isToday ? 'true' : undefined"
        @click="next"
      >
        <ChevronRight
          :size="20"
          aria-hidden="true"
        />
      </UiButton>
      <UiButton
        v-if="day !== today"
        variant="secondary"
        @click="goTo(today)"
      >
        Aujourd’hui
      </UiButton>
    </nav>

    <UiAlert
      v-if="error && !journal && status !== 'pending'"
      tone="error"
    >
      Impossible de charger cette journée.
      <UiButton
        variant="ghost"
        @click="refresh()"
      >
        Réessayer
      </UiButton>
    </UiAlert>
    <p
      class="sr-only"
      role="status"
    >
      {{ journal ? '' : 'Chargement…' }}
    </p>
    <!-- While the next day loads, the previous one stays, inert: the page keeps its height (no layout
         shift) and nothing can be written to the wrong day. The very first load keeps about a day's room. -->
    <div
      v-if="shownDay"
      class="flex flex-col gap-4"
      :class="{ 'opacity-60': !journal }"
      :inert="!journal || undefined"
      :aria-busy="!journal || undefined"
    >
      <DaySummary
        :day="shownDay"
        @build="building = true"
      />
      <div class="grid items-start gap-3 lg:grid-cols-2">
        <MealSection
          v-for="meal in shownDay.meals"
          :key="meal.slot"
          :meal="meal"
          @add="add"
          @edit="edit"
          @favorite="saveFavorite"
        />
      </div>
    </div>
    <div
      v-else-if="!error"
      class="min-h-144"
      aria-busy="true"
    />

    <AveragesCard :today="today" />

    <UiNavList
      label="Réglages nutrition"
      :items="shortcuts"
    />

    <UiStatus
      :message="notice"
      @dismiss="notice = null"
    />

    <template v-if="journal">
      <AddFoodDrawer
        v-model:open="adding"
        :meal-slot="addingTo"
        :log="log"
        :log-favorite="logFavorite"
        :initial-query="initialQuery"
        @done="done"
      />
      <EntryDrawer
        v-if="edited"
        v-model:open="editing"
        :entry="edited"
        :day="journal.day"
        :correct="correct"
        :remove="remove"
        @done="done"
      />
      <MealBuilderDrawer
        v-model:open="building"
        :day="journal.day"
        :log-many="logMany"
        @done="done"
      />
      <SaveFavoriteDrawer
        v-model:open="savingFavorite"
        :day="journal.day"
        :meal-slot="favoriteSlot"
        @saved="done(`« ${$event} » est dans tes repas favoris.`, favoriteSlot)"
      />
    </template>
  </div>
</template>
