import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ConnectivityBanner from '~/components/shell/ConnectivityBanner.vue'
import SettingsPage from '~/pages/reglages/index.vue'
import type { ApiClient } from '~/services/api/client'
import { ApiError } from '~/services/api/errors'

const { health } = vi.hoisted(() => ({ health: vi.fn<() => Promise<unknown>>() }))
const signedOut = new ApiError('unauthenticated', 401, 'x', 'x', 'shared.not_authenticated')

// Only the health endpoint is under test; the visitor is signed out.
mockNuxtImport('useApi', () => () => ({
  get: (path: string) => (path === '/api/health' ? health() : Promise.reject(signedOut)),
}) as unknown as ApiClient)

const unreachable = new ApiError('network', null, 'Connexion impossible. Vérifie ta connexion puis réessaie.', 'offline')
const degraded = new ApiError('server', 503, 'Une erreur est survenue. Réessaie dans un instant.', 'HTTP 503')

beforeEach(() => {
  clearNuxtData('system-health')
  health.mockReset()
})

describe('connectivity banner', () => {
  it('stays empty while the server answers', async () => {
    health.mockResolvedValue({ status: 'ok', checks: { database: 'up' } })

    const wrapper = await mountSuspended(ConnectivityBanner)
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toBe('')
  })

  it('shows the user message and retries on demand when the server is unreachable', async () => {
    health.mockRejectedValueOnce(unreachable).mockResolvedValueOnce({ status: 'ok', checks: {} })

    const wrapper = await mountSuspended(ConnectivityBanner)
    await flushPromises()

    expect(wrapper.text()).toContain('Connexion impossible')
    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(health).toHaveBeenCalledTimes(2)
    expect(wrapper.get('[role="status"]').text()).toBe('')
  })
})

describe('settings page', () => {
  it.each([
    [() => health.mockResolvedValue({ status: 'ok', checks: {} }), 'Connecté'],
    [() => health.mockRejectedValue(degraded), 'Service dégradé'],
    [() => health.mockRejectedValue(unreachable), 'Serveur injoignable'],
  ])('reports the server status (%#)', async (arrange, label) => {
    arrange()

    const wrapper = await mountSuspended(SettingsPage)
    await flushPromises()

    expect(wrapper.get('[data-testid="service-status"]').text()).toBe(label)
  })

  it('switches the theme from the radio group', async () => {
    health.mockResolvedValue({ status: 'ok', checks: {} })
    const wrapper = await mountSuspended(SettingsPage)

    await wrapper.get('input[value="light"]').setValue(true)

    expect(document.documentElement.dataset.theme).toBe('light')
    await wrapper.get('input[value="dark"]').setValue(true)
    expect(document.documentElement.dataset.theme).toBe('dark')
  })
})
