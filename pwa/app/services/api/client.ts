import { createFetch, FetchError } from 'ofetch'
import { ApiError, apiErrorFromResponse, networkError } from './errors'

export type QueryParams = Record<string, string | number | boolean | undefined>
export type JsonBody = Record<string, unknown> | unknown[]

export interface ApiClient {
  get<T>(path: string, query?: QueryParams): Promise<T>
  post<T>(path: string, body?: JsonBody): Promise<T>
  put<T>(path: string, body?: JsonBody): Promise<T>
  patch<T>(path: string, body?: JsonBody): Promise<T>
  delete(path: string): Promise<void>
}

// Reads are idempotent: retried once on network failures (ofetch reports them as 500) and gateway errors.
const READ_RETRY = { retry: 1, retryDelay: 300, retryStatusCodes: [500, 502, 503, 504] }

/**
 * Single entry point for every call to the Symfony API: same-origin session cookie,
 * JSON in/out, normalised {@link ApiError}s. Writes are never retried automatically.
 */
export function createApiClient(baseURL: string, fetchImplementation: typeof fetch = globalThis.fetch): ApiClient {
  const http = createFetch({
    fetch: fetchImplementation,
    defaults: {
      baseURL,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      retry: 0,
    },
  })

  async function send<T>(request: () => Promise<T>): Promise<T> {
    try {
      return await request()
    }
    catch (error) {
      throw asApiError(error)
    }
  }

  return {
    get: <T>(path: string, query?: QueryParams) => send(() => http<T>(path, { method: 'GET', query, ...READ_RETRY })),
    post: <T>(path: string, body?: JsonBody) => send(() => http<T>(path, { method: 'POST', body })),
    put: <T>(path: string, body?: JsonBody) => send(() => http<T>(path, { method: 'PUT', body })),
    patch: <T>(path: string, body?: JsonBody) =>
      send(() => http<T>(path, { method: 'PATCH', body, headers: { 'Content-Type': 'application/merge-patch+json' } })),
    delete: path => send(async () => {
      await http(path, { method: 'DELETE' })
    }),
  }
}

/**
 * Normalises anything thrown around an API call, including the NuxtError wrapper that
 * `useAsyncData` puts around the original {@link ApiError} (kept as `cause`).
 */
export function asApiError(error: unknown): ApiError {
  if (error instanceof ApiError) return error

  if (error instanceof Error && error.cause instanceof ApiError) return error.cause

  if (error instanceof FetchError && error.response) {
    return apiErrorFromResponse(error.response.status, error.data, error.response.headers.get('x-request-id'))
  }

  return networkError(error)
}
