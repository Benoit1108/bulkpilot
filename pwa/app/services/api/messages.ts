import type { ApiError } from './errors'

/**
 * User-facing messages for the API's stable error codes (its own messages are technical).
 * Each feature registers its codes (e.g. features/identity/messages.ts); unknown codes fall back
 * to the generic message of the error kind.
 */
const registry = new Map<string, string>([
  ['shared.not_authenticated', 'Ta session a expiré. Reconnecte-toi.'],
  ['shared.concurrent_update', 'Cette action a été faite deux fois en même temps. Recharge la page puis réessaie.'],
  ['shared.invalid_identifier', 'Élément introuvable.'],
])

export function registerErrorMessages(messages: Record<string, string>): void {
  for (const [code, message] of Object.entries(messages)) registry.set(code, message)
}

export function hasMessageFor(code: string): boolean {
  return registry.has(code)
}

/** Message for an error code received outside an API response (e.g. an OAuth redirect). */
export function messageForCode(code: string): string {
  return registry.get(code) ?? 'Une erreur est survenue. Réessaie.'
}

export function messageFor(error: ApiError): string {
  if (error.code && registry.has(error.code)) return registry.get(error.code)!
  if (error.kind === 'rate_limited') return 'Trop de tentatives. Réessaie dans quelques minutes.'
  return error.userMessage
}

/** Field errors from a 422 validation response, keyed by property path. */
export function fieldErrors(error: ApiError): Record<string, string> {
  return Object.fromEntries(error.violations.map(violation => [violation.propertyPath, violation.message]))
}
