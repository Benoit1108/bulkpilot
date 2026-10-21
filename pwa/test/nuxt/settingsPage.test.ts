import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SettingsPage from '~/pages/reglages/index.vue'
import { button } from '../support/dom'
import { ApiError } from '~/services/api/errors'

const { signOut, navigate } = vi.hoisted(() => ({ signOut: vi.fn<() => Promise<void>>(), navigate: vi.fn() }))
mockNuxtImport('useAccount', () => () => ({ account: ref({ firstName: 'Alex', email: 'alex@example.com', emailVerified: true, twoFactorEnabled: false }), signOut }))
mockNuxtImport('navigateTo', () => navigate)
mockNuxtImport('useApi', () => () => ({ get: () => Promise.resolve({ status: 'ok', checks: {} }) }))

beforeEach(() => {
  signOut.mockReset()
  navigate.mockReset()
})

describe('signing out from the settings', () => {
  it('signs out, then goes to the sign-in page', async () => {
    signOut.mockResolvedValue()
    const wrapper = await mountSuspended(SettingsPage, { route: '/reglages' })

    await button(wrapper, 'Se déconnecter').trigger('click')
    await flushPromises()

    expect(signOut).toHaveBeenCalledOnce()
    expect(navigate).toHaveBeenCalledWith('/connexion')
  })

  it('keeps the focus on the button while it signs out, then on why it failed', async () => {
    let fail = (_: Error) => {}
    signOut.mockImplementation(() => new Promise((_, reject) => {
      fail = reject
    }))
    const wrapper = await mountSuspended(SettingsPage, { route: '/reglages', attachTo: document.body })
    const leave = button(wrapper, 'Se déconnecter')
    ;(leave.element as HTMLButtonElement).focus()

    await leave.trigger('click')
    expect(leave.attributes('disabled'), 'still focusable: the focus never falls to the page').toBeUndefined()
    expect(leave.attributes('aria-disabled')).toBe('true')
    expect(document.activeElement).toBe(leave.element)
    await leave.trigger('click')
    expect(signOut, 'one sign-out at a time').toHaveBeenCalledOnce()
    fail(new ApiError('network', null, 'Connexion impossible.', 'offline'))
    await flushPromises()

    // The error takes the focus, as every error does: the user lands on what went wrong.
    expect(document.activeElement).toBe(wrapper.get('[role="alert"]').element)
    expect(wrapper.get('[role="alert"]').text()).toContain('Tu es toujours connecté sur cet appareil.')
    expect(navigate).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
