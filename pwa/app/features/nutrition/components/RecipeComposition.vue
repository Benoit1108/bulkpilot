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
  <table class="composition">
    <caption class="composition__caption">
      Composition calculée à partir des aliments
    </caption>
    <thead>
      <tr>
        <th scope="col">
          <span class="visually-hidden">Quantité</span>
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
        <th scope="row">
          {{ row.label }}<span class="composition__hint">{{ row.hint }}</span>
        </th>
        <td>{{ formatKcal(row.values.energyKcal) }}</td>
        <td>{{ formatMacro(row.values.protein) }}</td>
        <td>{{ formatMacro(row.values.carbohydrate) }}</td>
        <td>{{ formatMacro(row.values.fat) }}</td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
.composition {
  width: 100%;
  border-collapse: collapse;
  font-variant-numeric: tabular-nums;
}

.composition__caption {
  margin-bottom: var(--space-2);
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  text-align: left;
}

.composition th,
.composition td {
  padding: var(--space-2) var(--space-1);
  border-bottom: 1px solid var(--color-border);
  text-align: right;
  white-space: nowrap;
}

.composition th[scope='row'] {
  text-align: left;
  white-space: normal;
}

.composition thead th {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  font-weight: 600;
}

.composition__hint {
  display: block;
  color: var(--color-text-muted);
  font-size: var(--text-xs);
  font-weight: 400;
}
</style>
