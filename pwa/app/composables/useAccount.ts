import { fetchAccount, type Account, type AccountState, type ProfileInput } from '~/features/identity/account'
import { forgetDevice } from '~/services/offline/owner'

/**
 * The signed-in account, shared by the whole app. Sign-in state lives in the httpOnly session
 * cookie; this is only its client-side reflection.
 */
export const ACCOUNT_STATE_KEY = 'account'
/** Signing in cancelled the account's planned deletion: said once, on the page reached. */
export const DELETION_CANCELLED_KEY = 'account-deletion-cancelled'

export function useAccount() {
  const api = useApi()
  const account = useState<AccountState>(ACCOUNT_STATE_KEY, () => undefined)
  const deletionCancelled = useState(DELETION_CANCELLED_KEY, () => false)

  function signedIn(result: { account: Account | null, deletionCancelled?: boolean }): void {
    if (result.account) account.value = result.account
    if (result.deletionCancelled) deletionCancelled.value = true
  }

  async function load(): Promise<Account | null> {
    account.value = await fetchAccount(api)
    return account.value
  }

  /**
   * @returns whether a second factor is now expected (see {@link completeSignIn})
   */
  async function signIn(email: string, password: string): Promise<{ twoFactorRequired: boolean }> {
    const result = await api.post('/api/auth/sign-in', { email, password })
    if (!result.twoFactorRequired) signedIn(result)
    return { twoFactorRequired: result.twoFactorRequired }
  }

  /** Second step: a code from the authenticator app, or a recovery code. */
  async function completeSignIn(code: string): Promise<void> {
    signedIn(await api.post('/api/auth/sign-in/second-factor', { code }))
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
    // Signed out on purpose (a shared device): nothing of the account stays offline either.
    await closed()
  }

  /** The server says the device is signed out (expired, revoked, password reset elsewhere). */
  function forget(): void {
    account.value = null
    deletionCancelled.value = false
    clearNuxtData()
  }

  /**
   * Nothing of the account stays on the device, offline data included: signed out on purpose, or
   * the account closed (its deletion planned). The account is forgotten even if the device's
   * storage could not be emptied: the server side is already done, so that failure is reported,
   * not thrown at a user who can do nothing about it.
   */
  async function closed(): Promise<void> {
    try {
      await forgetDevice()
    }
    catch (error) {
      console.error('The device storage could not be emptied', error)
    }
    forget()
  }

  async function updateProfile(profile: ProfileInput): Promise<void> {
    account.value = await api.put('/api/me/profile', profile)
  }

  return { account: readonly(account), deletionCancelled, load, signIn, completeSignIn, register, signOut, forget, closed, updateProfile }
}
