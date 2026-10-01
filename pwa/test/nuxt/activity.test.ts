import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ActivityPage from '~/pages/reglages/activite.vue'
import { button } from '../support/dom'
import { activity, DATA_POLICY } from '../support/data'
import { createFakeApi } from '../support/fakeApi'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)
const fake = createFakeApi()
api.current = fake

beforeEach(() => {
  fake.reset()
  clearNuxtData()
  fake.on('GET /api/me/data-policy', () => DATA_POLICY)
})

const page = (count: number, start = 0) => Array.from({ length: count }, (_, index) => {
  const at = new Date(Date.UTC(2026, 8, 30, 8, 0) - (start + index) * 60_000).toISOString()
  // As the API sends it: no createdAt (the page adds it).
  const { createdAt: _createdAt, ...sent } = activity({ id: `0199a0b1-0000-7000-8000-${String(start + index).padStart(12, '0')}`, occurredAt: at })
  return sent
})

describe('the account activity', () => {
  it('lists what happened, in words, with what changed and who changed it', async () => {
    fake.on('GET /api/me/activity', () => [
      activity({ action: 'nutrition.target_set', details: { previous_kcal: 2200, kcal: 2350, effective_from: '2026-10-01', origin: 'coach' }, source: 'coach' }),
      activity({ id: '0199a0b1-0000-7000-8000-00000000a002', action: 'identity.password_changed' }),
    ])
    const wrapper = await mountSuspended(ActivityPage)
    await flushPromises()

    const items = wrapper.findAll('li')
    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('Objectif nutritionnel modifié')
    expect(items[0]!.text()).toContain('2\u202F200\u00A0kcal → 2\u202F350\u00A0kcal, à partir du 1 octobre, par le Coach')
    expect(items[1]!.text()).toContain('Mot de passe changé')
    expect(wrapper.findAll('button').some(b => b.text() === 'Voir plus'), 'fewer than a page: nothing more').toBe(false)
    wrapper.unmount()
  }, 10_000)

  it('reads the next page from the last one shown', async () => {
    const first = page(20)
    fake.on('GET /api/me/activity', (_body, params) => ((params as { query?: { before?: string } } | undefined)?.query?.before ? page(3, 20) : first))
    const wrapper = await mountSuspended(ActivityPage, { attachTo: document.body })
    await flushPromises()

    await button(wrapper, 'Voir plus').trigger('click')
    await flushPromises()
    expect(fake.calls.at(-1)?.params).toEqual({ query: { limit: 20, before: first.at(-1)!.occurredAt, beforeId: first.at(-1)!.id } })
    expect(wrapper.findAll('li')).toHaveLength(23)
    expect(wrapper.get('[data-testid="status"]').text()).toContain('3\u00A0actions de plus.')
    expect(document.activeElement, 'the first entry added has the focus').toBe(wrapper.findAll('li')[20]!.element)
    expect(wrapper.text()).toContain('Elles sont gardées un an.')
    wrapper.unmount()
  })

  it('says when there is nothing yet', async () => {
    fake.on('GET /api/me/activity', () => [])
    const wrapper = await mountSuspended(ActivityPage)
    await flushPromises()
    expect(wrapper.text()).toContain('Rien pour l’instant')
    wrapper.unmount()
  })
})
