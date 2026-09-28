import type { AccountState } from './account'

export interface RouteAccess {
  path: string
  fullPath: string
  public?: boolean
  guestOnly?: boolean
}

/**
 * Where a navigation should go given the sign-in state (pure, so it is unit-tested).
 * Returns `null` to let the navigation proceed.
 */
export function redirectFor(route: RouteAccess, account: AccountState): { path: string, query?: Record<string, string> } | null {
  if (account && route.guestOnly) return { path: '/' }
  if (!account && !route.public) {
    return route.fullPath === '/' ? { path: '/connexion' } : { path: '/connexion', query: { redirect: route.fullPath } }
  }
  return null
}

/**
 * Only paths of this app are followed after sign-in (no open redirect). Mirrors the API's
 * SafeRedirect (same rule, same test table): no other origin, no "//x" or "/\\x", and no control
 * or space character (browsers strip tabs and newlines, turning "/\t/evil" into "//evil").
 */
export function safeRedirect(value: unknown): string {
  // eslint-disable-next-line no-control-regex -- control characters are exactly what is rejected
  return typeof value === 'string' && /^\/(?![/\\])[^\u0000-\u0020\u007F\\]*$/.test(value) ? value : '/'
}
