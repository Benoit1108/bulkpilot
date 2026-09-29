import { ref } from 'vue'
import { offlineStore } from '~/services/offline/store'

/**
 * Whose data the device keeps. What is stored offline (a workout in progress, its queued writes,
 * cached plans) belongs to one account: another account signing in on the same device starts
 * afresh, and nothing is sent until the owner is signed in (a write sent with someone else's
 * session would land in their account).
 */
const OWNER = 'owner'
const resets = new Set<() => void>()
/** Reactive: what shows "reconnecte-toi" follows it. */
const signedIn = ref<string | null>(null)

/** Features forget their in-memory copies when the device data goes. */
export function onDeviceReset(reset: () => void): void {
  resets.add(reset)
}

/** The account the device writes for, once signed in. */
export function deviceOwner(): string | null {
  return signedIn.value
}

async function wipe(): Promise<void> {
  await (await offlineStore()).clear()
  resets.forEach(reset => reset())
}

export async function claimDevice(accountId: string): Promise<void> {
  const store = await offlineStore()
  const owner = await store.get<string>(OWNER)
  if (owner !== undefined && owner !== accountId) await wipe()
  if (owner !== accountId) await store.set(OWNER, accountId)
  signedIn.value = accountId
}

/** The session ended by itself (expired, revoked): the data waits for its owner to sign in again. */
export function releaseDevice(): void {
  signedIn.value = null
}

/** Signed out on purpose: nothing of the account stays on the device. */
export async function forgetDevice(): Promise<void> {
  signedIn.value = null
  await wipe()
}
