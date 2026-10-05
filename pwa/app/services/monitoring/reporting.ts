import { ApiError } from '~/services/api/errors'

/** Errors kept while the reporting is not ready yet (the first seconds); later ones are dropped. */
export const MAX_PENDING = 10

export interface ReportingConfig {
  errorReportingDsn: string | null
  environment: string
  /** The API's release (the bundle has its own). */
  release: string
}

/** Where an error was caught, as Sentry names it. */
export type Mechanism = 'onerror' | 'onunhandledrejection' | 'vue' | 'nuxt'

export interface Reporter {
  report: (error: unknown, mechanism: Mechanism) => void
}

/** The outcome of a start: reporting, nowhere to report, or the configuration could not be read yet. */
export type Started = 'reporting' | 'off' | 'retry'

/**
 * An answer of the API the app deals with (not found, conflict, validation, offline, signed out…):
 * the code that called handles it, it is not a defect. Only server errors (5xx) are worth a report.
 */
export function isExpected(error: unknown): boolean {
  return error instanceof ApiError && error.kind !== 'server'
}

/**
 * Error reporting (docs/MONITORING.md): errors are kept from the start; once the deployment's
 * configuration is read, either there is nowhere to report (dropped, nothing else loaded) or the
 * reporter is loaded and given what was kept, then every later error. Offline at start, it keeps
 * waiting and is started again when the network is back.
 */
export function createErrorReporting(options: {
  readConfig: () => Promise<ReportingConfig>
  loadReporter: (config: ReportingConfig & { errorReportingDsn: string }) => Promise<Reporter>
}) {
  const pending: Array<[unknown, Mechanism]> = []
  let state: 'waiting' | 'off' | Reporter = 'waiting'

  function keep(error: unknown, mechanism: Mechanism): void {
    if (isExpected(error)) return
    if (state === 'waiting') {
      if (pending.length < MAX_PENDING) pending.push([error, mechanism])
    }
    else if (state !== 'off') state.report(error, mechanism)
  }

  async function start(): Promise<Started> {
    if (state !== 'waiting') return state === 'off' ? 'off' : 'reporting'
    let config: ReportingConfig
    try {
      config = await options.readConfig()
    }
    catch {
      return 'retry'
    }
    const dsn = config.errorReportingDsn
    if (!dsn) return stop()
    let reporter: Reporter
    try {
      reporter = await options.loadReporter({ ...config, errorReportingDsn: dsn })
    }
    catch {
      // Its chunk is gone (a deployment since this page was loaded): nothing to report with.
      return stop()
    }
    state = reporter
    for (const [error, mechanism] of pending.splice(0)) reporter.report(error, mechanism)
    return 'reporting'
  }

  function stop(): 'off' {
    state = 'off'
    pending.length = 0
    return 'off'
  }

  return { keep, start }
}
