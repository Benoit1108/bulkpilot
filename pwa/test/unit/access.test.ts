import { describe, expect, it } from 'vitest'
import type { Account } from '~/features/identity/account'
import { redirectFor, safeRedirect } from '~/features/identity/access'

const alex = { id: '1', email: 'alex@example.com', emailVerified: true, firstName: 'Alex', birthDate: null, sex: null, heightCm: null, twoFactorEnabled: false, hasPassword: true } satisfies Account

describe('route access', () => {
  it('sends signed-out visitors of a private page to sign-in, remembering where they were going', () => {
    expect(redirectFor({ path: '/training', fullPath: '/training?x=1' }, null)).toEqual({ path: '/connexion', query: { redirect: '/training?x=1' } })
    expect(redirectFor({ path: '/', fullPath: '/' }, null)).toEqual({ path: '/connexion' })
  })

  it('lets anyone reach public pages', () => {
    expect(redirectFor({ path: '/verifier-email', fullPath: '/verifier-email', public: true }, null)).toBeNull()
    expect(redirectFor({ path: '/verifier-email', fullPath: '/verifier-email', public: true }, alex)).toBeNull()
  })

  it('keeps signed-in users away from sign-in and registration', () => {
    expect(redirectFor({ path: '/connexion', fullPath: '/connexion', public: true, guestOnly: true }, alex)).toEqual({ path: '/' })
    expect(redirectFor({ path: '/training', fullPath: '/training' }, alex)).toBeNull()
  })

  // Same table as api/tests/Unit/Identity/Presentation/SafeRedirectTest.php.
  it.each([
    ['/training', '/training'],
    ['/training?x=1&y=%2F', '/training?x=1&y=%2F'],
    ['/', '/'],
    ['', '/'],
    ['https://evil.example', '/'],
    ['//evil.example', '/'],
    ['/\\evil.example', '/'],
    ['/\t/evil.example', '/'],
    ['/\n/evil.example', '/'],
    ['/ /evil.example', '/'],
    ['training', '/'],
    ['javascript:alert(1)', '/'],
    [undefined, '/'],
    [['/a'], '/'],
  ])('only follows in-app redirects (%j → %s)', (value, expected) => {
    expect(safeRedirect(value)).toBe(expected)
  })
})
