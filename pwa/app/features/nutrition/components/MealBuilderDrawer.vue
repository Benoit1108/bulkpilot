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
    <div class="flex flex-col gap-3">
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
        class="text-muted empty:hidden"
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
          class="text-muted"
        >
          Définis d’abord tes objectifs&nbsp;: je compose avec ce qu’il te reste.
        </p>
        <template v-else>
          <p
            class="tabular-nums"
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
            class="text-muted"
          >
            Rien à proposer&nbsp;: l’objectif est presque atteint, ou il manque des aliments. Saisis des repas pour que tes habitudes servent de base.
          </p>
          <ol class="m-0 flex list-none flex-col gap-3 p-0">
            <li
              v-for="(proposal, index) in result.proposals"
              :key="index"
              class="flex flex-col gap-2 rounded-md border border-line bg-surface-raised p-3"
            >
              <h3
                :id="`proposal-${index}`"
                class="font-bold"
              >
                Proposition {{ index + 1 }}
              </h3>
              <ul class="text-sm text-muted">
                <li
                  v-for="item in proposal.items"
                  :key="item.foodId"
                >
                  {{ item.name }} · {{ formatGrams(item.grams) }}
                </li>
              </ul>
              <p class="font-semibold">
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
