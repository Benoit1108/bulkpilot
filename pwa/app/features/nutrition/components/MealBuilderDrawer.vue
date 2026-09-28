<script setup lang="ts">
import MacroLine from '~/features/nutrition/components/MacroLine.vue'
import { formatGrams } from '~/features/nutrition/format'
import { MEAL_SLOTS, slotAt, toSlot, type MealBuilderResult, type MealSlot, type Proposal } from '~/features/nutrition/nutrition'

/**
 * Meal Builder (spec §28): what is left, and up to 3 meals that fill it; one tap logs a whole meal.
 * The server picks the foods and the grams; this only shows them. Nothing is fetched while closed.
 */
const open = defineModel<boolean>('open', { required: true })
const props = defineProps<{
  day: string
  logMany: (items: Array<{ foodId: string, grams: number }>, slot: MealSlot) => Promise<void>
}>()
const emit = defineEmits<{ done: [message: string, slot: MealSlot] }>()

const api = useApi()
/** null = the rest of the day. */
const scope = ref<MealSlot | null>(null)
const into = ref<MealSlot | null>(slotAt())
const adding = ref<number | null>(null)
const { message, run, clear } = useSubmission()

const result = ref<MealBuilderResult | null>(null)
const loading = ref(false)
const error = ref(false)
let latest = 0

/** Recomputed on each opening and scope change only: the proposals depend on what was just logged. */
async function execute(): Promise<void> {
  const call = ++latest
  loading.value = true
  error.value = false
  try {
    const answer = await api.get('/api/nutrition/meal-builder', { query: { day: props.day, slot: scope.value ?? undefined } })
    if (call === latest) result.value = answer
  }
  catch {
    if (call === latest) error.value = true
  }
  finally {
    if (call === latest) loading.value = false
  }
}

watch(open, (value) => {
  if (value) {
    into.value = slotAt()
    void execute()
  }
  else clear()
}, { immediate: true })
watch(scope, () => {
  if (open.value) void execute()
})

const target = computed<MealSlot>(() => scope.value ?? into.value ?? slotAt())

function summary(proposal: Proposal): string {
  return proposal.items.map(item => item.name).join(' + ')
}

async function add(proposal: Proposal, index: number): Promise<void> {
  const items = proposal.items.map(item => ({ foodId: item.foodId, grams: item.grams }))
  const slot = target.value
  adding.value = index
  const done = await run(() => props.logMany(items, slot))
  adding.value = null
  if (done) {
    open.value = false
    emit('done', `Proposition ${index + 1} ajoutée ${toSlot(slot)}.`, slot)
  }
}
</script>

<template>
  <UiDrawer
    v-model:open="open"
    title="Que manger&#x202F;?"
  >
    <div class="builder">
      <UiSelect
        v-model="scope"
        label="Composer pour"
        :options="MEAL_SLOTS"
        placeholder="Le reste de la journée"
      />
      <UiSelect
        v-if="scope === null"
        v-model="into"
        label="Ajouter au repas"
        :options="MEAL_SLOTS"
        :nullable="false"
      />

      <UiAlert
        v-if="message"
        tone="error"
      >
        {{ message }}
      </UiAlert>
      <p
        class="muted"
        role="status"
      >
        <template v-if="loading">
          Calcul des propositions…
        </template>
      </p>
      <UiAlert
        v-if="error && !loading"
        tone="error"
      >
        Impossible de calculer des propositions.
      </UiAlert>
      <template v-else-if="result && !loading">
        <p
          v-if="!result.remaining"
          class="muted"
        >
          Définis d’abord tes objectifs&nbsp;: je compose avec ce qu’il te reste.
        </p>
        <template v-else>
          <p
            class="remaining"
            data-testid="remaining"
          >
            Restant&nbsp;:
            <strong>
              <MacroLine
                :energy-kcal="result.remaining.energyKcal"
                :protein="result.remaining.protein"
                :carbohydrate="result.remaining.carbohydrate"
                :fat="result.remaining.fat"
              />
            </strong>
          </p>
          <p
            v-if="!result.proposals.length"
            class="muted"
          >
            Rien à proposer&nbsp;: l’objectif est presque atteint, ou il manque des aliments. Saisis des repas pour que tes habitudes servent de base.
          </p>
          <ol class="proposals">
            <li
              v-for="(proposal, index) in result.proposals"
              :key="index"
              class="proposal"
            >
              <h3
                :id="`proposal-${index}`"
                class="proposal__title"
              >
                Proposition {{ index + 1 }}
              </h3>
              <ul class="proposal__items">
                <li
                  v-for="item in proposal.items"
                  :key="item.foodId"
                >
                  {{ item.name }} · {{ formatGrams(item.grams) }}
                </li>
              </ul>
              <p class="proposal__totals">
                <MacroLine
                  :energy-kcal="proposal.totals.energyKcal"
                  :protein="proposal.totals.protein"
                  :carbohydrate="proposal.totals.carbohydrate"
                  :fat="proposal.totals.fat"
                />
              </p>
              <UiButton
                variant="secondary"
                :loading="adding === index"
                :disabled="adding !== null && adding !== index"
                :aria-describedby="`proposal-${index}`"
                :title="summary(proposal)"
                @click="add(proposal, index)"
              >
                Ajouter {{ toSlot(target) }}
              </UiButton>
            </li>
          </ol>
        </template>
      </template>
    </div>
  </UiDrawer>
</template>

<style scoped>
.builder {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.muted {
  color: var(--color-text-muted);
}

.muted:empty {
  display: none;
}

.remaining {
  font-variant-numeric: tabular-nums;
}

.proposals {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}

.proposal {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
}

.proposal__title {
  font-weight: 700;
}

.proposal__items {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.proposal__totals {
  font-weight: 600;
}
</style>
