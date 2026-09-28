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
  <!-- Always mounted so screen readers announce content changes. -->
  <div
    class="update-prompt"
    :class="{ 'update-prompt--visible': visible }"
    role="status"
  >
    <template v-if="visible">
      <p>Une nouvelle version est disponible.</p>
      <div class="update-prompt__actions">
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

<style scoped>
.update-prompt {
  position: fixed;
  inset: auto var(--space-4) calc(var(--bottom-nav-height) + env(safe-area-inset-bottom) + var(--space-3));
  z-index: 20;
  max-width: var(--reading-max-width);
  margin: 0 auto;
}

.update-prompt--visible {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  background: var(--color-surface-raised);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-raised);
  font-size: var(--text-sm);
}

.update-prompt__actions {
  display: flex;
  gap: var(--space-2);
}

@media (min-width: 48rem) {
  .update-prompt {
    inset: auto var(--space-5) calc(env(safe-area-inset-bottom) + var(--space-5)) auto;
    width: 26rem;
  }
}
</style>
