import { createFetch, FetchError } from 'ofetch'
import type { paths } from '~/types/api'
import type { HttpMethod, PathParamsOf, PathWith, RequestBodyOf, ResponseOf } from './contract'
import { fillPath } from './contract'
import { ApiError, apiErrorFromResponse, networkError } from './errors'

export type QueryParams = Record<string, string | number | boolean | undefined>

type Params<P extends keyof paths, M extends HttpMethod>
  = PathParamsOf<P, M> extends undefined ? { path?: undefined } : { path: PathParamsOf<P, M> }

/**
 * Typed against the OpenAPI contract: `api.get('/api/me')` returns an `Account`,
 * a wrong path, body or parameter does not compile.
 */
export interface ApiClient {
  get<P extends PathWith<'get'>>(path: P, options?: Params<P, 'get'> & { query?: QueryParams }): Promise<ResponseOf<P, 'get'>>
  post<P extends PathWith<'post'>>(path: P, body?: RequestBodyOf<P, 'post'>, options?: Params<P, 'post'>): Promise<ResponseOf<P, 'post'>>
  put<P extends PathWith<'put'>>(path: P, body: RequestBodyOf<P, 'put'>, options?: Params<P, 'put'>): Promise<ResponseOf<P, 'put'>>
  /** A file sent as multipart/form-data (a photo): the browser sets the boundary. */
  upload<P extends PathWith<'post'>>(path: P, form: FormData, options?: Params<P, 'post'>): Promise<ResponseOf<P, 'post'>>
  delete<P extends PathWith<'delete'>>(path: P, options?: Params<P, 'delete'>): Promise<void>
}

export interface ApiClientOptions {
  fetch?: typeof fetch
  /** Called when the server says the device is no longer signed in (expired or revoked session). */
  onUnauthenticated?: () => void
}

// Reads are idempotent: retried once on network failures (ofetch reports them as 500) and gateway errors.
const READ_RETRY = { retry: 1, retryDelay: 300, retryStatusCodes: [500, 502, 503, 504] }

/**
 * Single entry point for every call to the Symfony API: same-origin session cookie, JSON in/out,
 * normalised {@link ApiError}s. Writes are never retried automatically.
 */
export function createApiClient(options: ApiClientOptions = {}): ApiClient {
  const http = createFetch({
    // Resolved per call, so a fetch replaced after start-up (tests, polyfills) is honoured.
    fetch: (input, init) => (options.fetch ?? globalThis.fetch)(input, init),
    defaults: {
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
      const apiError = asApiError(error)
      if (apiError.code === 'shared.not_authenticated') options.onUnauthenticated?.()
      throw apiError
    }
  }

  const url = (path: string, params?: { path?: unknown }) => fillPath(path, params?.path as Record<string, string> | undefined)
  const json = (body: unknown) => body as Record<string, unknown> | undefined

  return {
    get<P extends PathWith<'get'>>(path: P, params?: Params<P, 'get'> & { query?: QueryParams }) {
      return send(() => http<ResponseOf<P, 'get'>>(url(path, params), { method: 'GET', query: params?.query, ...READ_RETRY }))
    },
    post<P extends PathWith<'post'>>(path: P, body?: RequestBodyOf<P, 'post'>, params?: Params<P, 'post'>) {
      return send(() => http<ResponseOf<P, 'post'>>(url(path, params), { method: 'POST', body: json(body) }))
    },
    put<P extends PathWith<'put'>>(path: P, body: RequestBodyOf<P, 'put'>, params?: Params<P, 'put'>) {
      return send(() => http<ResponseOf<P, 'put'>>(url(path, params), { method: 'PUT', body: json(body) }))
    },
    upload<P extends PathWith<'post'>>(path: P, form: FormData, params?: Params<P, 'post'>) {
      return send(() => http<ResponseOf<P, 'post'>>(url(path, params), { method: 'POST', body: form }))
    },
    delete<P extends PathWith<'delete'>>(path: P, params?: Params<P, 'delete'>) {
      return send(async () => {
        await http(url(path, params), { method: 'DELETE' })
      })
    },
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
