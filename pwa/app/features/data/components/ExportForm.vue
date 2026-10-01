<script setup lang="ts">
import { EXPORT_CONTENTS, useExportDomains, type AccountExport } from '../exports'
import { hoursText, useDataPolicy } from '../policy'

/**
 * Asks for an export (spec §104): everything by default, or a period and some domains. The
 * archive is made in the background; the list below says when it is ready. One at a time.
 */
const props = defineProps<{ underWay: boolean }>()
const emit = defineEmits<{ requested: [exported: AccountExport] }>()

const api = useApi()
const { data: domains, error: domainsError, refresh } = useExportDomains()
const { data: policy } = useDataPolicy()
const chosen = ref<string[] | null>(null)
// Every domain until the user picks: nothing to tick for « tout exporter ».
const picked = computed({
  get: () => chosen.value ?? (domains.value ?? []).map(domain => domain.key),
  set: (keys: string[]) => {
    chosen.value = keys
  },
})
const from = ref('')
const to = ref('')
const { pending, message, fields, run, clear } = useSubmission()
const invalid = ref<{ domains?: string, to?: string }>({})
const domainsProblem = computed(() => invalid.value.domains ?? fields.value.domains ?? null)

// The period narrows what is dated only; the rest is always exported whole.
const periodHint = computed(() => {
  const dated = (domains.value ?? []).filter(domain => domain.periodFiltered).map(domain => domain.title)
  return dated.length ? `La période s’applique à\u00A0: ${dated.join(', ')}. Le reste est exporté en entier.` : undefined
})

function toggle(key: string, on: boolean): void {
  picked.value = on ? [...picked.value, key] : picked.value.filter(one => one !== key)
}

// What was wrong gets the focus: the error is read at once instead of sitting under a field.
const toField = ref<{ focus: () => void } | null>(null)
const boxes = ref<Array<{ focus: () => void }>>([])
async function focusInvalid(): Promise<void> {
  await nextTick()
  if (invalid.value.to) toField.value?.focus()
  else if (invalid.value.domains) boxes.value[0]?.focus()
}

async function submit(): Promise<void> {
  clear()
  invalid.value = {
    ...(picked.value.length === 0 && { domains: 'Choisis au moins une donnée à exporter.' }),
    ...(from.value && to.value && to.value < from.value && { to: 'La fin de la période vient avant son début.' }),
  }
  if (Object.keys(invalid.value).length) {
    await focusInvalid()
    return
  }
  const all = picked.value.length === (domains.value ?? []).length
  await run(async () => {
    emit('requested', await api.post('/api/exports', {
      ...(from.value && { from: from.value }),
      ...(to.value && { to: to.value }),
      ...(!all && { domains: picked.value }),
    }))
  })
}
</script>

<template>
  <form
    class="flex flex-col gap-4"
    novalidate
    @submit.prevent="submit"
  >
    <p class="text-sm text-muted">
      {{ EXPORT_CONTENTS }}
      <template v-if="policy">
        Une fois prête, l’archive reste téléchargeable {{ hoursText(policy.exportAvailableHours) }}.
      </template>
    </p>
    <UiAlert
      v-if="message"
      tone="error"
    >
      {{ message }}
    </UiAlert>
    <fieldset class="m-0 grid gap-3 border-0 p-0 *:min-w-0 sm:grid-cols-2">
      <legend class="mb-1 font-semibold">
        Période (facultative)
      </legend>
      <p
        v-if="periodHint"
        class="text-sm text-muted sm:col-span-2"
      >
        {{ periodHint }}
      </p>
      <UiInput
        v-model="from"
        label="Du"
        type="date"
        hint="Vide&nbsp;: depuis le début."
        :error="fields.from"
      />
      <UiInput
        ref="toField"
        v-model="to"
        label="Au"
        type="date"
        hint="Vide&nbsp;: jusqu’à aujourd’hui."
        :error="invalid.to ?? fields.to"
      />
    </fieldset>
    <UiLoadError
      v-if="domainsError && !domains"
      :focus-on-mount="false"
      @retry="refresh()"
    />
    <fieldset
      v-else
      class="m-0 flex flex-col border-0 p-0"
    >
      <legend class="mb-1 font-semibold">
        Données
      </legend>
      <UiCheckbox
        v-for="domain in domains ?? []"
        ref="boxes"
        :key="domain.key"
        :model-value="picked.includes(domain.key)"
        :label="domain.title"
        :invalid="Boolean(domainsProblem)"
        :describedby="domainsProblem ? 'export-domains-error' : undefined"
        @update:model-value="toggle(domain.key, $event)"
      />
      <p
        v-if="domainsProblem"
        id="export-domains-error"
        class="text-sm text-danger"
      >
        {{ domainsProblem }}
      </p>
    </fieldset>
    <UiButton
      type="submit"
      :loading="pending"
      :disabled="props.underWay"
      :aria-describedby="props.underWay ? 'export-under-way' : undefined"
    >
      Préparer mon export
    </UiButton>
    <p
      v-if="props.underWay"
      id="export-under-way"
      class="text-sm text-muted"
    >
      Un export est déjà en préparation&nbsp;: tu pourras en demander un autre une fois celui-ci terminé.
    </p>
  </form>
</template>
