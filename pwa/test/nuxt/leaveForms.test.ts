import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Component } from 'vue'
import { ACCOUNT_STATE_KEY } from '~/composables/useAccount'
import type { Account, AccountState } from '~/features/identity/account'
import NewFoodPage from '~/pages/nutrition/aliments/nouveau.vue'
import NewRecipePage from '~/pages/nutrition/recettes/nouvelle.vue'
import NewExercisePage from '~/pages/training/exercices/nouveau.vue'
import NewProgramPage from '~/pages/training/programmes/nouveau.vue'
import { field } from '../support/dom'
import { alex, createFakeApi } from '../support/fakeApi'
import { rice, recipe } from '../support/nutrition'
import { bench, ppl } from '../support/training'

const { api, navigate } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null }, navigate: vi.fn() }))
mockNuxtImport('useApi', () => () => api.current!.client)
mockNuxtImport('navigateTo', () => navigate)
const fake = createFakeApi()
api.current = fake

/** Each creation form: its page, the field typed into, what the API answers once saved. */
const forms: Array<{ name: string, page: Component, route: string, label: string, save: string, saved: unknown }> = [
  { name: 'program', page: NewProgramPage, route: '/training/programmes/nouveau', label: 'Nom du programme', save: 'POST /api/programs', saved: ppl },
  { name: 'exercise', page: NewExercisePage, route: '/training/exercices/nouveau', label: 'Nom', save: 'POST /api/exercises', saved: bench },
  { name: 'recipe', page: NewRecipePage, route: '/nutrition/recettes/nouvelle', label: 'Nom de la recette', save: 'POST /api/recipes', saved: recipe },
  { name: 'food', page: NewFoodPage, route: '/nutrition/aliments/nouveau', label: 'Nom', save: 'POST /api/foods', saved: rice },
]

function confirmAnswering(answer: boolean) {
  const confirm = vi.fn(() => answer)
  vi.stubGlobal('confirm', confirm)
  return confirm
}

beforeEach(() => {
  fake.reset()
  navigate.mockReset()
  clearNuxtData()
  // Signed in: a lost session (no account) leaves without asking.
  useState<AccountState>(ACCOUNT_STATE_KEY).value = alex as Account
  fake.on('GET /api/exercises', () => [])
  fake.on('GET /api/foods/recent', () => [])
})

describe.each(forms)('the $name form', ({ page, route, label, save, saved }) => {
  it('asks before leaving what was typed, and stays when the user says no', async () => {
    const wrapper = await mountSuspended(page, { route })
    const confirm = confirmAnswering(false)
    await field(wrapper, label).setValue('Nouveau')

    await useRouter().push('/')

    expect(confirm).toHaveBeenCalledOnce()
    expect(useRouter().currentRoute.value.path).toBe(route)
  })

  it('lets the user go once saved', async () => {
    fake.on(save, () => saved)
    const wrapper = await mountSuspended(page, { route })
    const confirm = confirmAnswering(false)
    await field(wrapper, label).setValue('Nouveau')

    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(fake.calls.map(call => call.route)).toContain(save)
    await useRouter().push('/')

    expect(confirm).not.toHaveBeenCalled()
    expect(useRouter().currentRoute.value.path).toBe('/')
  })
})
