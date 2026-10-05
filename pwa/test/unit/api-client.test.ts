import { afterEach, describe, expect, it, vi } from 'vitest'
import { asApiError, createApiClient } from '~/services/api/client'
import { ApiError, apiErrorFromResponse } from '~/services/api/errors'

function jsonResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': status >= 400 ? 'application/problem+json' : 'application/json', ...headers },
  })
}

function clientReturning(...responses: Array<Response | Error>) {
  const fetchMock = vi.fn<typeof fetch>()
  for (const response of responses) {
    if (response instanceof Error) fetchMock.mockRejectedValueOnce(response)
    else fetchMock.mockResolvedValueOnce(response)
  }

  return { client: createApiClient({ fetch: fetchMock }), fetchMock }
}

afterEach(() => {
  vi.useRealTimers()
})

describe('api client', () => {
  it('prefixes the base URL and sends JSON with the same-origin session cookie', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(201, { id: 'abc' }))

    await expect(client.post('/api/auth/sign-in', { email: 'a@b.c', password: 'x' })).resolves.toEqual({ id: 'abc' })

    const [url, init] = fetchMock.mock.calls[0]!
    expect(String(url)).toBe('/api/auth/sign-in')
    expect(init?.method).toBe('POST')
    expect(init?.credentials).toBe('same-origin')
    expect(init?.body).toBe(JSON.stringify({ email: 'a@b.c', password: 'x' }))
  })

  it('normalises problem+json errors with violations and trace id', async () => {
    const { client } = clientReturning(jsonResponse(422, {
      title: 'Validation failed',
      detail: 'kg: must be positive',
      violations: [{ propertyPath: 'kg', message: 'must be positive' }, { nope: true }],
    }, { 'X-Request-Id': 'req-1' }))

    const error = await client.post('/api/auth/register', { firstName: '', email: 'x', password: 'y' }).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({
      kind: 'validation',
      status: 422,
      traceId: 'req-1',
      violations: [{ propertyPath: 'kg', message: 'must be positive' }],
      technicalMessage: 'HTTP 422 Validation failed: kg: must be positive',
    })
  })

  it('maps a network failure to a user friendly error', async () => {
    vi.useFakeTimers()
    const { client } = clientReturning(new TypeError('Failed to fetch'), new TypeError('Failed to fetch'))

    const pending = client.get('/api/health').catch((e: unknown) => e)
    await vi.runAllTimersAsync()
    const error = await pending

    expect(error).toMatchObject({ kind: 'network', status: null })
    expect((error as ApiError).userMessage).toContain('Connexion impossible')
  })

  it('retries idempotent reads once on a transient gateway error', async () => {
    vi.useFakeTimers()
    const { client, fetchMock } = clientReturning(jsonResponse(503, {}), jsonResponse(200, { status: 'ok' }))

    const result = client.get('/api/health')
    await vi.runAllTimersAsync()

    await expect(result).resolves.toEqual({ status: 'ok' })
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('retries idempotent reads once when the network drops', async () => {
    vi.useFakeTimers()
    const { client, fetchMock } = clientReturning(new TypeError('Failed to fetch'), jsonResponse(200, { status: 'ok' }))

    const result = client.get('/api/health')
    await vi.runAllTimersAsync()

    await expect(result).resolves.toEqual({ status: 'ok' })
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('sends a file as multipart, letting the browser set the boundary', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(201, { id: 'photo' }))
    const form = new FormData()
    form.append('day', '2026-09-29')

    await expect(client.upload('/api/progression/photos', form)).resolves.toEqual({ id: 'photo' })

    const [, init] = fetchMock.mock.calls[0]!
    expect(init?.method).toBe('POST')
    expect(init?.body).toBe(form)
    expect(new Headers(init?.headers).get('Content-Type')).toBeNull()
  })

  it('never retries writes', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(503, {}), jsonResponse(201, {}))

    await expect(client.post('/api/auth/sign-out')).rejects.toMatchObject({ kind: 'server', status: 503 })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it.each([
    [401, 'unauthenticated'],
    [403, 'forbidden'],
    [404, 'not_found'],
    [409, 'conflict'],
    [429, 'rate_limited'],
    [500, 'server'],
  ])('maps HTTP %i to %s', async (status, kind) => {
    const { client } = clientReturning(jsonResponse(status, {}))

    await expect(client.delete('/api/me/sessions/{id}', { path: { id: 'x' } })).rejects.toMatchObject({ kind, status })
  })

  it('sends full replacements with PUT', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(200, { ok: true }))

    await expect(client.put('/api/me/profile', { firstName: 'Alex' })).resolves.toEqual({ ok: true })
    expect(fetchMock.mock.calls[0]![1]?.method).toBe('PUT')
  })

  it('fills and encodes path parameters', async () => {
    const { client, fetchMock } = clientReturning(new Response(null, { status: 204 }))

    await client.delete('/api/me/sessions/{id}', { path: { id: 'a/b c' } })

    expect(String(fetchMock.mock.calls[0]![0])).toBe('/api/me/sessions/a%2Fb%20c')
  })

  it('tells the app when the session is gone, and only then', async () => {
    const onUnauthenticated = vi.fn()
    const fetchMock = vi.fn<typeof fetch>()
      .mockResolvedValueOnce(jsonResponse(401, { code: 'identity.invalid_credentials' }))
      .mockResolvedValueOnce(jsonResponse(401, { code: 'shared.not_authenticated' }))
    const client = createApiClient({ fetch: fetchMock, onUnauthenticated })

    await expect(client.post('/api/auth/sign-in', { email: 'a@b.c', password: 'x' })).rejects.toMatchObject({ code: 'identity.invalid_credentials' })
    expect(onUnauthenticated).not.toHaveBeenCalled()
    await expect(client.get('/api/me')).rejects.toMatchObject({ kind: 'unauthenticated' })
    expect(onUnauthenticated).toHaveBeenCalledOnce()
  })
})

describe('apiErrorFromResponse', () => {
  it('says the service restarts on a gateway error, not that the network is down', () => {
    for (const status of [502, 503, 504]) {
      expect(apiErrorFromResponse(status, null).userMessage).toBe('Le service redémarre (mise à jour). Réessaie dans une minute.')
    }
    // A dependency of ours down: its own code says so.
    expect(apiErrorFromResponse(503, { code: 'nutrition.product_database_unavailable' }).userMessage).toBe('Une erreur est survenue. Réessaie dans un instant.')
    expect(apiErrorFromResponse(500, null).userMessage).toBe('Une erreur est survenue. Réessaie dans un instant.')
  })
})

describe('asApiError', () => {
  it('unwraps the ApiError that useAsyncData keeps as cause', () => {
    const original = new ApiError('network', null, 'Connexion impossible', 'boom')

    expect(asApiError(new Error('wrapped', { cause: original }))).toBe(original)
  })

  it('treats anything unknown as a network failure', () => {
    expect(asApiError('weird')).toMatchObject({ kind: 'network', status: null })
  })
})
