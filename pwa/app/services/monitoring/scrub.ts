/**
 * What the PWA's error reports may say (docs/MONITORING.md): where it broke and how, never who or
 * with what. The same rules as the API's SentryEventScrubber: no query strings; e-mails and long
 * tokens masked, UUIDs kept (opaque ids).
 */
const EMAIL = /[\w.+-]+@[\w-]+(?:\.[\w-]+)+/gu
const TOKEN = /(?<![\w-])(?![0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?![\w-]))[\w\-+]{32,}={0,2}/gu
const URL_QUERY = /(\bhttps?:\/\/[^\s?#]+|(?<![\w/])\/[\w\-./]*)[?#]\S*/gu

export function redact(text: string): string {
  return text.replace(EMAIL, '[email]').replace(TOKEN, '[token]')
}

/** A URL without its query and fragment, its path redacted. */
export function withoutQuery(url: string): string {
  return redact(url.split(/[?#]/u, 1)[0] ?? '')
}

/** The text redacted, and every URL in it without its query and fragment. */
export function withoutQueries(text: string): string {
  return redact(text.replace(URL_QUERY, '$1'))
}
