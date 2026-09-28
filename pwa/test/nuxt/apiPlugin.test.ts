import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { alex } from '../support/fakeApi'

const { navigate } = vi.hoisted(() => ({ navigate: vi.fn() }))
mockNuxtImport('navigateTo', () => navigate)

const signedOut = () => new Response(JSON.stringify({ code: 'shared.not_authenticated' }), { status: 401, headers: { 'Content-Type': 'application/problem+json' } })

beforeEach(() => {
  navigate.mockReset()
  vi.stubGlobal('fetch', vi.fn(async () => signedOut()))
})

describe('api plugin', () => {
  it('sends a signed-in user whose session was lost back to sign-in, remembering the page', async () => {
    await useRouter().push('/training')
    useState('account').value = alex

    await useNuxtApp().$api.get('/api/me').catch(() => {})

    expect(useState('account').value).toBeNull()
    expect(navigate).toHaveBeenCalledWith({ path: '/connexion', query: { redirect: '/training' } })
  })

  it('does nothing for a visitor who was never signed in (their first check is a 401)', async () => {
    useState('account').value = undefined

    await useNuxtApp().$api.get('/api/me').catch(() => {})

    expect(navigate).not.toHaveBeenCalled()
  })
})
