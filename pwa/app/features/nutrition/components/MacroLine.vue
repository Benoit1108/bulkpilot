<script setup lang="ts">
import { formatKcal, formatMacro } from '~/features/nutrition/format'

/** « 826 kcal · Prot. 51 g · Gluc. 101 g · Lip. 22 g », the abbreviations spelled out for screen readers. */
withDefaults(defineProps<{
  energyKcal?: number | null
  protein: number
  carbohydrate: number
  fat: number
}>(), { energyKcal: null })
</script>

<template>
  <span class="tabular-nums [&_abbr]:no-underline"><template v-if="energyKcal !== null">{{ formatKcal(energyKcal) }} · </template><abbr title="Protéines">Prot.</abbr>&nbsp;{{ formatMacro(protein) }} · <abbr title="Glucides">Gluc.</abbr>&nbsp;{{ formatMacro(carbohydrate) }} · <abbr title="Lipides">Lip.</abbr>&nbsp;{{ formatMacro(fat) }}</span>
</template>
