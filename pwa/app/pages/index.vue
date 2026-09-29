<script setup lang="ts">
import { LayoutDashboard } from 'lucide-vue-next'
import EmailStatus from '~/features/identity/components/EmailStatus.vue'

definePageMeta({ title: 'Accueil' })

const { account } = useAccount()

const dateFormat = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
const now = ref(new Date())
const today = computed(() => dateFormat.format(now.value))

// An installed PWA can stay open overnight: refresh the date when it comes back to the foreground.
function refreshDate(): void {
  if (document.visibilityState === 'visible') now.value = new Date()
}
onMounted(() => document.addEventListener('visibilitychange', refreshDate))
onBeforeUnmount(() => document.removeEventListener('visibilitychange', refreshDate))
</script>

<template>
  <div class="flex flex-col gap-4">
    <div>
      <p
        v-if="account"
        class="text-lg font-semibold"
      >
        Bonjour {{ account.firstName }}
      </p>
      <!-- "Lundi 28 septembre": only the first letter is capitalised in French. -->
      <p class="text-muted first-letter:uppercase">
        {{ today }}
      </p>
    </div>

    <UiAlert
      v-if="account && !account.emailVerified"
      tone="info"
    >
      <EmailStatus
        :account="account"
        compact
      />
    </UiAlert>

    <UiCard>
      <UiEmptyState
        :icon="LayoutDashboard"
        title="Ton tableau de bord arrive"
        description="Il s’assemblera à partir de tes vraies données&nbsp;: pesées, repas, séances et recommandations."
      />
    </UiCard>
  </div>
</template>
