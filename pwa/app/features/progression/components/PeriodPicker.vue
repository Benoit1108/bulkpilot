<script setup lang="ts">
import { PERIODS, type Period } from '../progression'

/** The span of a chart (spec §55): short labels, what they stand for read out after them. */
const period = defineModel<Period>({ required: true })
defineProps<{ name: string }>()
</script>

<template>
  <UiSegmented
    v-model="period"
    legend="Période"
    hide-legend
    :name="name"
    :options="PERIODS"
  />
</template>
