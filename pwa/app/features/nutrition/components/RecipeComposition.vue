<script setup lang="ts">
import { formatGrams, formatKcal, formatMacro } from '~/features/nutrition/format'
import type { Recipe } from '~/features/nutrition/nutrition'

const props = defineProps<{ recipe: Recipe }>()

const rows = computed(() => [
  { label: 'Par portion', hint: formatGrams(props.recipe.servingGrams), values: props.recipe.perServing },
  { label: 'Pour 100 g', hint: props.recipe.cookedWeightGrams ? 'plat cuit' : 'poids cru', values: props.recipe.per100g },
  { label: 'Plat entier', hint: formatGrams(props.recipe.totalGrams), values: props.recipe.total },
])
</script>

<template>
  <table class="w-full border-collapse tabular-nums [&_:is(td,th)]:border-b [&_:is(td,th)]:border-line [&_:is(td,th)]:px-1 [&_:is(td,th)]:py-2 [&_:is(td,th[scope=col])]:text-right [&_:is(td,th[scope=col])]:whitespace-nowrap">
    <caption class="mb-2 text-left text-sm text-muted">
      Composition calculée à partir des aliments
    </caption>
    <thead>
      <tr class="[&_th]:text-sm [&_th]:font-semibold [&_th]:text-muted">
        <th scope="col">
          <span class="sr-only">Quantité</span>
        </th>
        <th scope="col">
          Énergie
        </th>
        <th scope="col">
          <abbr title="Protéines">Prot.</abbr>
        </th>
        <th scope="col">
          <abbr title="Glucides">Gluc.</abbr>
        </th>
        <th scope="col">
          <abbr title="Lipides">Lip.</abbr>
        </th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="row in rows"
        :key="row.label"
      >
        <th
          scope="row"
          class="text-left"
        >
          {{ row.label }}<span class="block text-xs font-normal text-muted">{{ row.hint }}</span>
        </th>
        <td>{{ formatKcal(row.values.energyKcal) }}</td>
        <td>{{ formatMacro(row.values.protein) }}</td>
        <td>{{ formatMacro(row.values.carbohydrate) }}</td>
        <td>{{ formatMacro(row.values.fat) }}</td>
      </tr>
    </tbody>
  </table>
</template>
