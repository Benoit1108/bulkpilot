import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { alex, apiError, createFakeApi } from '../support/fakeApi'

const { api } = vi.hoisted(() => ({ api: { current: null as ReturnType<typeof createFakeApi> | null } }))
mockNuxtImport('useApi', () => () => api.current!.client)

const fake = createFakeApi()
api.current = fake

beforeEach(() => {
  fake.reset()
  useState('account').value = undefined
})

describe('useAccount', () => {
  it('starts unknown, then reflects the server session', async () => {
    const { account, load } = useAccount()
    expect(account.value).toBeUndefined()

    fake.on('GET /api/me', () => alex)
    await load()
    expect(account.value).toEqual(alex)
  })

  it('treats a 401 on load as signed out, and other failures as errors', async () => {
    const { account, load } = useAccount()

    await load()
    expect(account.value).toBeNull()

    fake.on('GET /api/me', () => {
      throw apiError(503, null)
    })
    await expect(load()).rejects.toMatchObject({ status: 503 })
  })

  it('stays signed in when the server could not sign the device out', async () => {
    const { account, signOut } = useAccount()
    useState('account').value = alex

    await expect(signOut()).rejects.toBeDefined()
    expect(account.value, 'a failed sign-out must not look successful on a shared device').toEqual(alex)
  })

  it('forgets the account once the server confirmed the sign-out', async () => {
    fake.on('POST /api/auth/sign-out', () => undefined)
    const { account, signOut } = useAccount()
    useState('account').value = alex

    await signOut()

    expect(account.value).toBeNull()
  })

  it('keeps the account returned by profile updates', async () => {
    fake.on('PUT /api/me/profile', () => ({ ...alex, heightCm: 178 }))
    const { account, updateProfile } = useAccount()

    await updateProfile({ firstName: 'Alex', heightCm: 178 })

    expect(account.value).toMatchObject({ heightCm: 178 })
  })
})
