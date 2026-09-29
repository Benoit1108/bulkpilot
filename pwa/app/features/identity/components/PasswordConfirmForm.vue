<script setup lang="ts">
defineProps<{
  submitLabel: string
  pending: boolean
  error?: string
}>()
const emit = defineEmits<{ submit: [password: string], cancel: [] }>()

const password = ref('')
</script>

<template>
  <form
    class="flex flex-col gap-4"
    novalidate
    @submit.prevent="emit('submit', password)"
  >
    <UiInput
      v-model="password"
      label="Confirme ton mot de passe"
      type="password"
      autocomplete="current-password"
      required
      autofocus
      :error="error"
    />
    <div class="flex flex-wrap gap-2">
      <UiButton
        type="submit"
        :loading="pending"
      >
        {{ submitLabel }}
      </UiButton>
      <UiButton
        variant="ghost"
        @click="emit('cancel')"
      >
        Annuler
      </UiButton>
    </div>
  </form>
</template>
