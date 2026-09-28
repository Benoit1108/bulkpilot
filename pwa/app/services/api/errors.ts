/**
 * Normalised error thrown by the API client. Components display `userMessage`;
 * `technicalMessage` and `traceId` are for logs and bug reports, never shown raw.
 */
export type ApiErrorKind = 'network' | 'unauthenticated' | 'forbidden' | 'not_found' | 'validation' | 'conflict' | 'rate_limited' | 'server'

export interface ApiViolation {
  propertyPath: string
  message: string
}

export class ApiError extends Error {
  override readonly name = 'ApiError'

  constructor(
    readonly kind: ApiErrorKind,
    readonly status: number | null,
    readonly userMessage: string,
    readonly technicalMessage: string,
    readonly code: string | null = null,
    readonly violations: readonly ApiViolation[] = [],
    readonly traceId: string | null = null,
  ) {
    super(technicalMessage)
  }
}

const userMessages: Record<ApiErrorKind, string> = {
  network: 'Connexion impossible. Vérifie ta connexion puis réessaie.',
  unauthenticated: 'Ta session a expiré. Reconnecte-toi.',
  forbidden: 'Tu n’as pas accès à cette ressource.',
  not_found: 'Élément introuvable.',
  validation: 'Certaines informations sont invalides.',
  conflict: 'Cette donnée a été modifiée entre-temps. Recharge puis réessaie.',
  rate_limited: 'Trop de tentatives. Patiente quelques instants.',
  server: 'Une erreur est survenue. Réessaie dans un instant.',
}

function kindFromStatus(status: number): ApiErrorKind {
  if (status === 401) return 'unauthenticated'
  if (status === 403) return 'forbidden'
  if (status === 404) return 'not_found'
  if (status === 409 || status === 412) return 'conflict'
  if (status === 422 || status === 400) return 'validation'
  if (status === 429) return 'rate_limited'
  return 'server'
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null
}

function readViolations(body: Record<string, unknown>): ApiViolation[] {
  const raw = body.violations
  if (!Array.isArray(raw)) return []

  return raw.filter(isRecord).flatMap((violation) => {
    const { propertyPath, message } = violation
    return typeof propertyPath === 'string' && typeof message === 'string' ? [{ propertyPath, message }] : []
  })
}

/**
 * Builds an ApiError from an HTTP status and a problem+json (RFC 9457) body.
 */
export function apiErrorFromResponse(status: number, body: unknown, traceId: string | null = null): ApiError {
  const kind = kindFromStatus(status)
  const problem = isRecord(body) ? body : {}
  const detail = typeof problem.detail === 'string' ? problem.detail : null
  const title = typeof problem.title === 'string' ? problem.title : null
  const code = typeof problem.code === 'string' ? problem.code : null

  return new ApiError(
    kind,
    status,
    userMessages[kind],
    `HTTP ${status}${title ? ` ${title}` : ''}${detail ? `: ${detail}` : ''}`,
    code,
    readViolations(problem),
    traceId,
  )
}

export function networkError(cause: unknown): ApiError {
  const reason = cause instanceof Error ? cause.message : String(cause)
  return new ApiError('network', null, userMessages.network, `Network failure: ${reason}`)
}
