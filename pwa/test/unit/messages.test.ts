import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'
import '~/features/identity/messages'
import '~/features/nutrition/messages'
import '~/features/training/messages'
import '~/features/progression/messages'
import '~/features/coach/messages'
import { ApiError } from '~/services/api/errors'
import { fieldErrors, hasMessageFor, messageFor, messageForCode } from '~/services/api/messages'

// Every business error code the API can return (exported by `make openapi`).
const apiErrorCodes = JSON.parse(readFileSync(new URL('../../openapi/error-codes.json', import.meta.url), 'utf8')) as string[]

describe('error messages', () => {
  it.each(apiErrorCodes)('has a French message for the API code %s', (code) => {
    expect(hasMessageFor(code)).toBe(true)
  })

  it('translates known error codes', () => {
    const error = new ApiError('unauthenticated', 401, 'Ta session a expiré.', 'x', 'identity.invalid_credentials')

    expect(messageFor(error)).toBe('Email ou mot de passe incorrect.')
    expect(messageForCode('identity.external_sign_in_cancelled')).toBe('Connexion annulée.')
  })

  it('falls back to the generic message, with a specific one for throttling', () => {
    expect(messageFor(new ApiError('server', 500, 'Une erreur est survenue.', 'x', 'unknown.code'))).toBe('Une erreur est survenue.')
    expect(messageFor(new ApiError('rate_limited', 429, 'Trop de tentatives.', 'x'))).toContain('quelques minutes')
    expect(messageForCode('unknown.code')).toBe('Une erreur est survenue. Réessaie.')
  })

  it('indexes validation errors by field', () => {
    const error = new ApiError('validation', 422, 'x', 'x', null, [{ propertyPath: 'email', message: 'Adresse invalide.' }])

    expect(fieldErrors(error)).toEqual({ email: 'Adresse invalide.' })
  })
})
