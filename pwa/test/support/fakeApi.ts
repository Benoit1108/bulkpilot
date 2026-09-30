import { vi } from 'vitest'
import type { ApiClient } from '~/services/api/client'
import { ApiError } from '~/services/api/errors'

type Handler = (body?: unknown, params?: { path?: Record<string, string> }) => unknown

/**
 * In-memory stand-in for the API client: routes "METHOD /path" to handlers and records calls.
 * Unrouted calls fail like a signed-out request.
 */
export function createFakeApi() {
  const routes = new Map<string, Handler>()
  const calls: Array<{ route: string, body?: unknown, params?: unknown }> = []

  function dispatch(method: string, path: string, body?: unknown, params?: { path?: Record<string, string> }): Promise<unknown> {
    const route = `${method} ${path}`
    calls.push({ route, body, params })
    const handler = routes.get(route)
    if (!handler) return Promise.reject(new ApiError('unauthenticated', 401, 'Ta session a expiré.', route, 'shared.not_authenticated'))
    try {
      return Promise.resolve(handler(body, params))
    }
    catch (error) {
      return Promise.reject(error)
    }
  }

  const client = {
    get: vi.fn((path: string, params?: { path?: Record<string, string> }) => dispatch('GET', path, undefined, params)),
    post: vi.fn((path: string, body?: unknown, params?: { path?: Record<string, string> }) => dispatch('POST', path, body, params)),
    put: vi.fn((path: string, body?: unknown, params?: { path?: Record<string, string> }) => dispatch('PUT', path, body, params)),
    upload: vi.fn((path: string, form: FormData, params?: { path?: Record<string, string> }) => dispatch('POST', path, form, params)),
    delete: vi.fn((path: string, params?: { path?: Record<string, string> }) => dispatch('DELETE', path, undefined, params)),
  } as unknown as ApiClient

  return {
    client,
    calls,
    on(route: string, handler: Handler) {
      routes.set(route, handler)
    },
    reset() {
      routes.clear()
      calls.length = 0
    },
  }
}

export function apiError(status: number, code: string | null, violations: Array<{ propertyPath: string, message: string }> = []): ApiError {
  const kind = status === 401 ? 'unauthenticated' : status === 404 ? 'not_found' : status === 409 ? 'conflict' : status === 429 ? 'rate_limited' : status === 422 ? 'validation' : 'server'
  return new ApiError(kind, status, 'Une erreur est survenue.', `HTTP ${status}`, code, violations)
}

export const alex = {
  id: '0199a0b1-7c3e-7d4a-9b2f-000000000001',
  email: 'alex@example.com',
  emailVerified: false,
  firstName: 'Alex',
  birthDate: null,
  sex: null,
  heightCm: null,
  twoFactorEnabled: false,
  hasPassword: true,
  timeZone: 'Europe/Paris',
}
