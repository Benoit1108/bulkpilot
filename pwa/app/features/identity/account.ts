import type { ApiClient } from '~/services/api/client'
import { asApiError } from '~/services/api/client'
import type { components } from '~/types/api'

export type Account = components['schemas']['Account']
export type DeviceSession = components['schemas']['Session']
export type ProfileInput = components['schemas']['Account.ProfileInput']

/** `undefined` until the first check, `null` when signed out. */
export type AccountState = Account | null | undefined

export async function fetchAccount(api: ApiClient): Promise<Account | null> {
  try {
    return await api.get('/api/me')
  }
  catch (error) {
    if (asApiError(error).kind === 'unauthenticated') return null
    throw error
  }
}
