import { ApiError } from '~/services/api/errors'
import type { Mechanism } from './reporting'
import { redact, withoutQueries, withoutQuery } from './scrub'

/**
 * A minimal client for Sentry-compatible error reporting (Sentry, GlitchTip): an error becomes one
 * event in an envelope POSTed to the project's endpoint. Written here rather than the SDK (over
 * 100 kB): it sends only what we decide — type, redacted message, stack frames without queries,
 * page path, environment and release; no user, cookie, header, body or trail.
 */
export const MAX_REPORTS = 10
const MAX_FRAMES = 50
/** Under the 64 kB a page may keep in flight with `keepalive`, whatever the message. */
const MAX_VALUE = 1000
const MAX_FUNCTION = 200

export interface Endpoint {
  url: string
  dsn: string
}

export interface Frame {
  function?: string
  filename?: string
  lineno?: number
  colno?: number
}

/** `https://<key>@<host>[/<path>]/<project>` → the envelope endpoint, authenticated by the public key. */
export function endpointOf(dsn: string): Endpoint | null {
  let parsed: URL
  try {
    parsed = new URL(dsn)
  }
  catch {
    return null
  }
  const segments = parsed.pathname.split('/').filter(Boolean)
  const project = segments.pop()
  if (!parsed.username || !project || !/^https?:$/u.test(parsed.protocol)) return null
  const base = `${parsed.protocol}//${parsed.host}${segments.length ? `/${segments.join('/')}` : ''}`
  const query = new URLSearchParams({ sentry_key: parsed.username, sentry_version: '7', sentry_client: 'bulkpilot/1' })
  return { url: `${base}/api/${project}/envelope/?${query}`, dsn }
}

/** Chrome (`at f (url:1:2)`) and Firefox/Safari (`f@url:1:2`) stacks, the oldest call first as Sentry expects. */
export function framesOf(stack: string | undefined): Frame[] {
  const frames: Frame[] = []
  for (const line of (stack ?? '').split('\n')) {
    const match = /^\s*at (?:(.+?) \()?(.+?):(\d+):(\d+)\)?$/u.exec(line) ?? /^\s*(.*?)@(.+?):(\d+):(\d+)$/u.exec(line)
    if (!match) continue
    const [, name, file, lineno, colno] = match
    frames.push({
      ...(name && { function: redact(name).slice(0, MAX_FUNCTION) }),
      filename: withoutQuery(file ?? ''),
      lineno: Number(lineno),
      colno: Number(colno),
    })
  }
  return frames.slice(0, MAX_FRAMES).reverse()
}

function eventId(): string {
  return crypto.randomUUID().replaceAll('-', '')
}

/** A browser error event (window `error`): duck-typed, the class is not everywhere (workers, tests). */
function isErrorEvent(value: unknown): value is { message: string, filename: string, lineno: number, colno: number } {
  return typeof value === 'object' && value !== null && typeof (value as { filename?: unknown }).filename === 'string' && typeof (value as { message?: unknown }).message === 'string'
}

interface Described {
  type: string
  value: string
  frames: Frame[]
  tags: Record<string, string>
}

/**
 * What a thrown value says: its type, message and stack for a real Error (a server error of the
 * API: its status and title, never the detail, which may quote what was typed, and the request id
 * that finds it in the API's own reports); one frame from where a browser error event points; the
 * text alone for anything else (no stack: it would be the reporter's own).
 */
function describe(error: unknown): Described {
  if (error instanceof ApiError) {
    return { type: 'ApiError', value: error.technicalMessage.split(':', 1)[0] ?? 'HTTP error', frames: framesOf(error.stack), tags: error.traceId ? { request_id: error.traceId } : {} }
  }
  if (error instanceof Error) {
    return { type: error.name || 'Error', value: withoutQueries(error.message), frames: framesOf(error.stack), tags: {} }
  }
  if (isErrorEvent(error)) {
    const frame = error.filename ? [{ filename: withoutQuery(error.filename), lineno: error.lineno, colno: error.colno }] : []
    return { type: 'Error', value: withoutQueries(error.message || 'Script error'), frames: frame, tags: {} }
  }
  return { type: 'Error', value: typeof error === 'string' ? withoutQueries(error) : 'Non-error thrown', frames: [], tags: {} }
}

export function eventOf(error: unknown, context: { environment: string, release: string, apiRelease: string, page: string, mechanism: Mechanism, now: Date }) {
  const { type, value, frames, tags } = describe(error)
  return {
    event_id: eventId(),
    timestamp: context.now.getTime() / 1000,
    platform: 'javascript',
    level: 'error',
    environment: context.environment,
    release: context.release,
    sdk: { name: 'bulkpilot.browser', version: '1' },
    tags: { api_release: context.apiRelease, ...tags },
    request: { url: withoutQuery(context.page) },
    exception: {
      values: [{
        type,
        value: value.slice(0, MAX_VALUE),
        mechanism: { type: context.mechanism, handled: false },
        ...(frames.length && { stacktrace: { frames } }),
      }],
    },
  }
}

/** The same error twice (a loop, a re-render) is one report. */
function fingerprintOf(event: ReturnType<typeof eventOf>): string {
  const [exception] = event.exception.values
  const top = exception?.stacktrace?.frames.at(-1)
  return `${exception?.type}|${exception?.value}|${top?.filename}:${top?.lineno}:${top?.colno}`
}

export function envelopeOf(endpoint: Endpoint, event: ReturnType<typeof eventOf>, now: Date): string {
  return [
    JSON.stringify({ event_id: event.event_id, sent_at: now.toISOString(), dsn: endpoint.dsn }),
    JSON.stringify({ type: 'event' }),
    JSON.stringify(event),
  ].join('\n')
}

/**
 * Sends errors to the endpoint, each different error once and at most {@link MAX_REPORTS} per page
 * load (an error loop must not flood it). Fire and forget: a report that fails is not retried, it
 * never breaks the app.
 */
export function createReporter(options: {
  dsn: string
  environment: string
  release: string
  apiRelease: string
  page: () => string
  send?: (url: string, body: string) => Promise<unknown>
}) {
  const endpoint = endpointOf(options.dsn)
  const send = options.send ?? ((url: string, body: string) => fetch(url, { method: 'POST', body, keepalive: true }))
  const seen = new Set<string>()

  return {
    report(error: unknown, mechanism: Mechanism): void {
      if (!endpoint || seen.size >= MAX_REPORTS) return
      const now = new Date()
      const event = eventOf(error, { environment: options.environment, release: options.release, apiRelease: options.apiRelease, page: options.page(), mechanism, now })
      const fingerprint = fingerprintOf(event)
      if (seen.has(fingerprint)) return
      seen.add(fingerprint)
      void send(endpoint.url, envelopeOf(endpoint, event, now)).catch(() => {})
    },
  }
}
