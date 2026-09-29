<script setup lang="ts">
import ProgramForm from '~/features/training/components/ProgramForm.vue'
import { isProgramKey } from '~/features/training/cacheKeys'
import type { Program } from '~/features/training/training'

definePageMeta({ title: 'Nouveau programme' })

async function onSaved(program: Program): Promise<void> {
  clearNuxtData(isProgramKey)
  await navigateTo(`/training/programmes/${program.id}`)
}
</script>

<template>
  <div class="subpage">
    <ShellBackLink
      to="/training/programmes"
      label="Mes programmes"
    />
    <UiCard>
      <ProgramForm @saved="onSaved" />
    </UiCard>
  </div>
</template>
