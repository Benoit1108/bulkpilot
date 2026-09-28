import { afterEach, describe, expect, it, vi } from 'vitest'
import { asApiError, createApiClient } from '~/services/api/client'
import { ApiError } from '~/services/api/errors'

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

  return { client: createApiClient('https://app.test/api', fetchMock), fetchMock }
}

afterEach(() => {
  vi.useRealTimers()
})

describe('api client', () => {
  it('prefixes the base URL and sends JSON with the same-origin session cookie', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(201, { id: 'abc' }))

    await expect(client.post('/weights', { kg: 48.2 })).resolves.toEqual({ id: 'abc' })

    const [url, init] = fetchMock.mock.calls[0]!
    expect(String(url)).toBe('https://app.test/api/weights')
    expect(init?.method).toBe('POST')
    expect(init?.credentials).toBe('same-origin')
    expect(init?.body).toBe(JSON.stringify({ kg: 48.2 }))
  })

  it('normalises problem+json errors with violations and trace id', async () => {
    const { client } = clientReturning(jsonResponse(422, {
      title: 'Validation failed',
      detail: 'kg: must be positive',
      violations: [{ propertyPath: 'kg', message: 'must be positive' }, { nope: true }],
    }, { 'X-Request-Id': 'req-1' }))

    const error = await client.post('/weights', { kg: -1 }).catch((e: unknown) => e)

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

    const pending = client.get('/health').catch((e: unknown) => e)
    await vi.runAllTimersAsync()
    const error = await pending

    expect(error).toMatchObject({ kind: 'network', status: null })
    expect((error as ApiError).userMessage).toContain('Connexion impossible')
  })

  it('retries idempotent reads once on a transient gateway error', async () => {
    vi.useFakeTimers()
    const { client, fetchMock } = clientReturning(jsonResponse(503, {}), jsonResponse(200, { status: 'ok' }))

    const result = client.get('/health')
    await vi.runAllTimersAsync()

    await expect(result).resolves.toEqual({ status: 'ok' })
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('retries idempotent reads once when the network drops', async () => {
    vi.useFakeTimers()
    const { client, fetchMock } = clientReturning(new TypeError('Failed to fetch'), jsonResponse(200, { status: 'ok' }))

    const result = client.get('/health')
    await vi.runAllTimersAsync()

    await expect(result).resolves.toEqual({ status: 'ok' })
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('never retries writes', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(503, {}), jsonResponse(201, {}))

    await expect(client.post('/weights', {})).rejects.toMatchObject({ kind: 'server', status: 503 })
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('sends merge-patch for partial updates', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(200, {}))

    await client.patch('/me', { firstName: 'Alex' })

    const headers = new Headers(fetchMock.mock.calls[0]![1]?.headers)
    expect(headers.get('content-type')).toBe('application/merge-patch+json')
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

    await expect(client.delete('/x')).rejects.toMatchObject({ kind, status })
  })

  it('sends full replacements with PUT', async () => {
    const { client, fetchMock } = clientReturning(jsonResponse(200, { ok: true }))

    await expect(client.put('/goals/1', { kg: 55 })).resolves.toEqual({ ok: true })
    expect(fetchMock.mock.calls[0]![1]?.method).toBe('PUT')
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
