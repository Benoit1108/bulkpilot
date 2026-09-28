<script setup lang="ts">
import { formatKcal, formatNumber, formatSignedKcal } from '~/features/nutrition/format'
import type { EnergyEstimate } from '~/features/nutrition/nutrition'

/** Spec §24: every step of the calculation is shown, nothing is a black box. */
defineProps<{ estimate: EnergyEstimate }>()
</script>

<template>
  <ol
    class="steps"
    aria-label="Détail du calcul"
  >
    <li class="step">
      <span class="step__label">Métabolisme de base (Mifflin-St&nbsp;Jeor)</span>
      <span class="step__value">{{ formatKcal(estimate.basalMetabolicRate) }}</span>
      <span class="step__hint">{{ estimate.age }}&nbsp;ans · {{ estimate.heightCm }}&nbsp;cm · {{ formatNumber(estimate.weightKg) }}&nbsp;kg</span>
    </li>
    <li class="step">
      <span class="step__label">Activité × {{ formatNumber(estimate.activityLevel + estimate.trainingAllowance, 2) }}</span>
      <span class="step__value">{{ formatKcal(estimate.totalExpenditure) }}</span>
      <span class="step__hint">Mode de vie {{ formatNumber(estimate.activityLevel, 2) }} + entraînement {{ formatNumber(estimate.trainingAllowance, 2) }} ({{ estimate.trainingSessionsPerWeek }}&nbsp;séances/semaine)</span>
    </li>
    <li class="step">
      <span class="step__label">{{ estimate.dailyAdjustment >= 0 ? 'Surplus' : 'Déficit' }}</span>
      <span class="step__value">{{ formatSignedKcal(estimate.dailyAdjustment) }}</span>
      <span class="step__hint">{{ formatNumber(estimate.weeklyWeightChangeKg, 2) }}&nbsp;kg/semaine × 7&nbsp;700&nbsp;kcal/kg ÷ 7</span>
    </li>
    <li class="step step--total">
      <span class="step__label">Objectif estimé</span>
      <span class="step__value">{{ formatKcal(estimate.energyKcal) }}</span>
      <span class="step__hint">Arrondi à 10&nbsp;kcal. Un point de départ&nbsp;: le coach l’ajustera avec ta courbe de poids.</span>
    </li>
  </ol>
</template>

<style scoped>
.steps {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}

.step {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 0 var(--space-3);
  padding: var(--space-2) 0;
  border-bottom: 1px solid var(--color-border);
}

.step__label {
  font-weight: 600;
}

.step__value {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}

.step__hint {
  grid-column: 1 / -1;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.step--total {
  border-bottom: 0;
}

.step--total .step__value {
  color: var(--color-accent-text);
}
</style>
