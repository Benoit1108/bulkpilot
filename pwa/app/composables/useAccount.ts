import { fetchAccount, type Account, type AccountState, type ProfileInput } from '~/features/identity/account'

/**
 * The signed-in account, shared by the whole app. Sign-in state lives in the httpOnly session
 * cookie; this is only its client-side reflection.
 */
export const ACCOUNT_STATE_KEY = 'account'

export function useAccount() {
  const api = useApi()
  const account = useState<AccountState>(ACCOUNT_STATE_KEY, () => undefined)

  async function load(): Promise<Account | null> {
    account.value = await fetchAccount(api)
    return account.value
  }

  /**
   * @returns whether a second factor is now expected (see {@link completeSignIn})
   */
  async function signIn(email: string, password: string): Promise<{ twoFactorRequired: boolean }> {
    const result = await api.post('/api/auth/sign-in', { email, password })
    if (!result.twoFactorRequired) account.value = result.account
    return { twoFactorRequired: result.twoFactorRequired }
  }

  /** Second step: a code from the authenticator app, or a recovery code. */
  async function completeSignIn(code: string): Promise<void> {
    account.value = (await api.post('/api/auth/sign-in/second-factor', { code })).account
  }

  async function register(firstName: string, email: string, password: string): Promise<void> {
    account.value = await api.post('/api/auth/register', { firstName, email, password })
  }

  /**
   * Only once the server confirmed: a failed sign-out must not look like a successful one on a
   * shared device. Cached personal data is dropped with the account.
   */
  async function signOut(): Promise<void> {
    await api.post('/api/auth/sign-out')
    forget()
  }

  /** The server says the device is signed out (expired, revoked, password reset elsewhere). */
  function forget(): void {
    account.value = null
    clearNuxtData()
  }

  async function updateProfile(profile: ProfileInput): Promise<void> {
    account.value = await api.put('/api/me/profile', profile)
  }

  return { account: readonly(account), load, signIn, completeSignIn, register, signOut, forget, updateProfile }
}
