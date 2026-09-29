<script setup lang="ts">
// A new version is never activated behind the user's back (e.g. in the middle of a workout).
const { needRefresh, applyUpdate } = usePwaUpdate()
const dismissed = ref(false)
const visible = computed(() => needRefresh.value && !dismissed.value)

watch(needRefresh, () => {
  dismissed.value = false
})
</script>

<template>
  <!-- Always mounted so screen readers announce content changes. Above the bottom bar on phones. -->
  <div
    class="fixed inset-x-4 bottom-above-nav z-20 mx-auto max-w-reading md:right-6 md:bottom-safe-6 md:left-auto md:w-104"
    :class="{ 'flex flex-wrap items-center justify-between gap-3 rounded-md border border-line bg-surface-raised px-4 py-3 text-sm shadow-raised': visible }"
    role="status"
  >
    <template v-if="visible">
      <p>Une nouvelle version est disponible.</p>
      <div class="flex gap-2">
        <UiButton
          variant="ghost"
          @click="dismissed = true"
        >
          Plus tard
        </UiButton>
        <UiButton @click="applyUpdate()">
          Mettre à jour
        </UiButton>
      </div>
    </template>
  </div>
</template>
