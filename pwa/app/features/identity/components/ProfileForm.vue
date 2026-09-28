<script setup lang="ts">
import type { Account } from '~/features/identity/account'
import { HEIGHT_CM } from '~/features/identity/rules'

const props = defineProps<{ account: Account }>()

const { updateProfile } = useAccount()
const { pending, message, fields, run } = useSubmission()
const saved = ref(false)

const firstName = ref(props.account.firstName)
const birthDate = ref(props.account.birthDate ?? '')
const sex = ref<'female' | 'male' | null>(props.account.sex ?? null)
const heightCm = ref<number | string | null>(props.account.heightCm ?? '')

const sexOptions = [
  { value: 'female', label: 'Femme' },
  { value: 'male', label: 'Homme' },
] as const

async function submit(): Promise<void> {
  saved.value = false
  saved.value = await run(() => updateProfile({
    firstName: firstName.value,
    birthDate: birthDate.value || null,
    sex: sex.value,
    heightCm: heightCm.value === '' || heightCm.value === null ? null : Math.round(Number(heightCm.value)),
  }))
}
</script>

<template>
  <form
    class="form"
    novalidate
    @submit.prevent="submit"
  >
    <p class="form__intro">
      Utilisé pour estimer tes besoins. Ton poids se renseigne dans Progression.
    </p>
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <UiAlert
      v-if="saved"
      tone="success"
    >
      Profil enregistré.
    </UiAlert>
    <UiInput
      v-model="firstName"
      label="Prénom"
      autocomplete="given-name"
      required
      :error="fields.firstName"
    />
    <UiInput
      v-model="birthDate"
      label="Date de naissance"
      type="date"
      autocomplete="bday"
      :error="fields.birthDate"
    />
    <UiSelect
      v-model="sex"
      label="Sexe"
      :options="sexOptions"
      :error="fields.sex"
    />
    <UiInput
      v-model="heightCm"
      label="Taille"
      type="number"
      inputmode="numeric"
      suffix="cm"
      :min="HEIGHT_CM.min"
      :max="HEIGHT_CM.max"
      :error="fields.heightCm"
    />
    <UiButton
      type="submit"
      :loading="pending"
    >
      Enregistrer
    </UiButton>
  </form>
</template>
