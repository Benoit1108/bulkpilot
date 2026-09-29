<script setup lang="ts">
import { formatKcal, formatNumber, formatSignedKcal } from '~/features/nutrition/format'
import type { EnergyEstimate } from '~/features/nutrition/nutrition'

/** Spec §24: every step of the calculation is shown, nothing is a black box. */
defineProps<{ estimate: EnergyEstimate }>()
</script>

<template>
  <ol
    class="m-0 flex list-none flex-col gap-2 p-0"
    aria-label="Détail du calcul"
  >
    <li class="grid grid-cols-[1fr_auto] gap-x-3 border-b border-line py-2">
      <span class="font-semibold">Métabolisme de base (Mifflin-St&nbsp;Jeor)</span>
      <span class="font-bold tabular-nums">{{ formatKcal(estimate.basalMetabolicRate) }}</span>
      <span class="col-span-full text-sm text-muted">{{ estimate.age }}&nbsp;ans · {{ estimate.heightCm }}&nbsp;cm · {{ formatNumber(estimate.weightKg) }}&nbsp;kg</span>
    </li>
    <li class="grid grid-cols-[1fr_auto] gap-x-3 border-b border-line py-2">
      <span class="font-semibold">Activité × {{ formatNumber(estimate.activityLevel + estimate.trainingAllowance, 2) }}</span>
      <span class="font-bold tabular-nums">{{ formatKcal(estimate.totalExpenditure) }}</span>
      <span class="col-span-full text-sm text-muted">Mode de vie {{ formatNumber(estimate.activityLevel, 2) }} + entraînement {{ formatNumber(estimate.trainingAllowance, 2) }} ({{ estimate.trainingSessionsPerWeek }}&nbsp;séances/semaine)</span>
    </li>
    <li class="grid grid-cols-[1fr_auto] gap-x-3 border-b border-line py-2">
      <span class="font-semibold">{{ estimate.dailyAdjustment >= 0 ? 'Surplus' : 'Déficit' }}</span>
      <span class="font-bold tabular-nums">{{ formatSignedKcal(estimate.dailyAdjustment) }}</span>
      <span class="col-span-full text-sm text-muted">{{ formatNumber(estimate.weeklyWeightChangeKg, 2) }}&nbsp;kg/semaine × 7&nbsp;700&nbsp;kcal/kg ÷ 7</span>
    </li>
    <li class="grid grid-cols-[1fr_auto] gap-x-3 py-2">
      <span class="font-semibold">Objectif estimé</span>
      <span class="font-bold text-accent-ink tabular-nums">{{ formatKcal(estimate.energyKcal) }}</span>
      <span class="col-span-full text-sm text-muted">Arrondi à 10&nbsp;kcal. Un point de départ&nbsp;: le coach l’ajustera avec ta courbe de poids.</span>
    </li>
  </ol>
</template>
