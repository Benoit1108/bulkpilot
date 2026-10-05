import { describe, expect, it, vi } from 'vitest'
import { ApiError } from '~/services/api/errors'
import { createReporter, endpointOf, envelopeOf, eventOf, framesOf, MAX_REPORTS } from '~/services/monitoring/report'
import { createErrorReporting, MAX_PENDING, type ReportingConfig } from '~/services/monitoring/reporting'
import { redact, withoutQueries, withoutQuery } from '~/services/monitoring/scrub'

const UUID = '0199a0b1-0000-7000-8000-00000000f001'
const DSN = 'https://abc123@errors.test/42'
const config = (dsn: string | null): ReportingConfig => ({ errorReportingDsn: dsn, environment: 'production', release: 'abc123' })
const context = { environment: 'production', release: 'sha-bundle', apiRelease: 'sha-api', page: 'https://app.test/nutrition?recherche=riz', mechanism: 'onerror' as const, now: new Date('2026-10-02T12:00:00Z') }
const CHROME = `TypeError: Cannot read properties of undefined (reading 'kcal') for alex@example.com
    at total (https://app.test/_nuxt/day.js?v=3:10:5)
    at https://app.test/_nuxt/entry.js:2:30`

describe('what an error report may say', () => {
  it('masks e-mails and long tokens, keeps the opaque ids', () => {
    expect(redact(`alex@example.com opened ${UUID} with token ${'a'.repeat(40)}`)).toBe(`[email] opened ${UUID} with token [token]`)
  })

  it('drops the query and the fragment of a URL, also inside a text', () => {
    expect(withoutQuery('https://app.test/reinitialiser?token=secret#x')).toBe('https://app.test/reinitialiser')
    expect(withoutQueries('GET /api/foods?q=riz failed, see https://app.test/x?y=1 — is it ?')).toBe('GET /api/foods failed, see https://app.test/x — is it ?')
  })
})

describe('the reporter', () => {
  it('sends to the project envelope endpoint, authenticated by the public key only', () => {
    expect(endpointOf(DSN)?.url).toBe('https://errors.test/api/42/envelope/?sentry_key=abc123&sentry_version=7&sentry_client=bulkpilot%2F1')
    expect(endpointOf('https://abc123@glitchtip.test/sub/7')?.url).toMatch(/^https:\/\/glitchtip\.test\/sub\/api\/7\/envelope\/\?/u)
    expect(endpointOf('not a dsn')).toBeNull()
    expect(endpointOf('https://errors.test/42')).toBeNull()
  })

  it('reads Chrome and Firefox stacks, the oldest call first, files without their query', () => {
    expect(framesOf(CHROME)).toEqual([
      { filename: 'https://app.test/_nuxt/entry.js', lineno: 2, colno: 30 },
      { function: 'total', filename: 'https://app.test/_nuxt/day.js', lineno: 10, colno: 5 },
    ])
    expect(framesOf('total@https://app.test/_nuxt/day.js?v=1:10:5')).toEqual([{ function: 'total', filename: 'https://app.test/_nuxt/day.js', lineno: 10, colno: 5 }])
  })

  it('says where and how it broke, never who: no user, cookies, headers or query', () => {
    const error = new TypeError(`Nothing for alex@example.com at /api/foods?q=${'s'.repeat(40)}`)
    error.stack = CHROME
    const event = eventOf(error, context)

    expect(event).toMatchObject({
      platform: 'javascript',
      level: 'error',
      environment: 'production',
      release: 'sha-bundle',
      sdk: { name: 'bulkpilot.browser', version: '1' },
      tags: { api_release: 'sha-api' },
      timestamp: Date.parse('2026-10-02T12:00:00Z') / 1000,
      request: { url: 'https://app.test/nutrition' },
      exception: { values: [{ type: 'TypeError', value: 'Nothing for [email] at /api/foods', mechanism: { type: 'onerror', handled: false }, stacktrace: { frames: framesOf(CHROME) } }] },
    })
    expect(event.event_id).toMatch(/^[0-9a-f]{32}$/u)
    expect(Object.keys(event)).not.toContain('user')
  })

  it('wraps the event in an envelope of three lines', () => {
    const now = new Date('2026-10-02T12:00:00Z')
    const event = eventOf('boom', { ...context, now })
    const [header, item, body] = envelopeOf(endpointOf(DSN)!, event, now).split('\n')

    expect(JSON.parse(header!)).toEqual({ event_id: event.event_id, sent_at: '2026-10-02T12:00:00.000Z', dsn: DSN })
    expect(JSON.parse(item!)).toEqual({ type: 'event' })
    // A string thrown has no stack of its own: none rather than the reporter's.
    expect(JSON.parse(body!).exception.values[0]).toEqual({ type: 'Error', value: 'boom', mechanism: { type: 'onerror', handled: false } })
  })

  it('sends a few reports per page load at most, each error once, and never throws when sending fails', async () => {
    const send = vi.fn().mockRejectedValue(new Error('offline'))
    const reporter = createReporter({ dsn: DSN, environment: 'production', release: 'r', apiRelease: 'a', page: () => 'https://app.test/', send })
    const looping = new Error('again')
    for (let i = 0; i < 5; i++) reporter.report(looping, 'vue')
    for (let i = 0; i < MAX_REPORTS + 3; i++) reporter.report(new Error(String(i)), 'vue')
    await Promise.resolve()

    expect(send).toHaveBeenCalledTimes(MAX_REPORTS)
    expect(send.mock.calls[0]?.[0]).toBe(endpointOf(DSN)?.url)
  })

  it('tells of a server error by its status and title only, with the request id', () => {
    const error = new ApiError('server', 500, 'Une erreur est survenue.', 'HTTP 500 Internal Server Error: note « genou douloureux » refused', null, [], 'req-123')
    const event = eventOf(error, context)

    expect(event.exception.values[0]?.value).toBe('HTTP 500 Internal Server Error')
    expect(event.tags).toEqual({ api_release: 'sha-api', request_id: 'req-123' })
  })

  it('locates a browser error event without a stack by its file, line and column', () => {
    const event = eventOf({ type: 'error', message: 'Script error.', filename: 'https://app.test/_nuxt/a.js?v=2', lineno: 3, colno: 9 }, context)

    expect(event.exception.values[0]).toMatchObject({ value: 'Script error.', stacktrace: { frames: [{ filename: 'https://app.test/_nuxt/a.js', lineno: 3, colno: 9 }] } })
  })

  it('cuts long messages and function names', () => {
    const error = new Error('a b '.repeat(1250))
    error.stack = `Error\n    at ${'f.'.repeat(250)} (https://app.test/_nuxt/a.js:1:1)`
    const value = eventOf(error, context).exception.values[0]

    expect(value?.value).toHaveLength(1000)
    expect(value?.stacktrace?.frames[0]?.function).toHaveLength(200)
  })

  it('reads anonymous Firefox frames', () => {
    expect(framesOf('@https://app.test/_nuxt/a.js:4:2')).toEqual([{ filename: 'https://app.test/_nuxt/a.js', lineno: 4, colno: 2 }])
  })
})

describe('error reporting', () => {
  it('loads nothing when the deployment reports no error', async () => {
    const loadReporter = vi.fn()
    const reporting = createErrorReporting({ readConfig: async () => config(null), loadReporter })
    reporting.keep(new Error('early'), 'vue')

    expect(await reporting.start()).toBe('off')
    expect(loadReporter).not.toHaveBeenCalled()
  })

  it('offline at start, keeps waiting and reports once it can read its configuration', async () => {
    const reporter = { report: vi.fn() }
    const readConfig = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValue(config(DSN))
    const reporting = createErrorReporting({ readConfig, loadReporter: vi.fn().mockResolvedValue(reporter) })
    const early = new Error('in the gym, offline')
    reporting.keep(early, 'onerror')

    expect(await reporting.start()).toBe('retry')
    expect(await reporting.start()).toBe('reporting')
    expect(reporter.report).toHaveBeenCalledExactlyOnceWith(early, 'onerror')
  })

  it('stops quietly when the reporter cannot be loaded (its chunk is gone after a deployment)', async () => {
    const reporting = createErrorReporting({ readConfig: async () => config(DSN), loadReporter: vi.fn().mockRejectedValue(new Error('Failed to fetch dynamically imported module')) })

    await expect(reporting.start()).resolves.toBe('off')
  })

  it('sends what happened before, then every later error, but no answer the app handles', async () => {
    const reporter = { report: vi.fn() }
    const loadReporter = vi.fn().mockResolvedValue(reporter)
    const reporting = createErrorReporting({ readConfig: async () => config(DSN), loadReporter })
    const early = new Error('early')
    reporting.keep(early, 'vue')
    reporting.keep(new ApiError('not_found', 404, 'Élément introuvable.', 'HTTP 404 Not Found'), 'onunhandledrejection')

    expect(await reporting.start()).toBe('reporting')
    expect(loadReporter).toHaveBeenCalledWith(config(DSN))
    expect(reporter.report).toHaveBeenCalledExactlyOnceWith(early, 'vue')
    const later = new Error('later')
    reporting.keep(later, 'onerror')
    reporting.keep(new ApiError('network', null, 'Connexion impossible.', 'Network failure'), 'onerror')
    expect(reporter.report).toHaveBeenLastCalledWith(later, 'onerror')
    expect(reporter.report).toHaveBeenCalledTimes(2)
  })

  it('keeps a few errors only while it waits', async () => {
    const reporter = { report: vi.fn() }
    let ready: (value: ReportingConfig) => void = () => {}
    const configRead = new Promise<ReportingConfig>((resolve) => {
      ready = resolve
    })
    const reporting = createErrorReporting({ readConfig: () => configRead, loadReporter: vi.fn().mockResolvedValue(reporter) })
    for (let i = 0; i < MAX_PENDING + 5; i++) reporting.keep(new Error(String(i)), 'vue')
    const started = reporting.start()
    ready(config(DSN))
    await started

    expect(reporter.report).toHaveBeenCalledTimes(MAX_PENDING)
  })
})
