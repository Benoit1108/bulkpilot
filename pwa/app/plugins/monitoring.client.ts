import { createErrorReporting, type Mechanism } from '~/services/monitoring/reporting'

/**
 * Error reporting (docs/MONITORING.md), only where this deployment says where to (GET
 * /api/client-config): the reporter is fetched then, after the first paint, so the initial bundle
 * stays as it is. Errors raised before are kept and sent once it is ready. Runs before the other
 * plugins so that their errors are seen too.
 */
export default defineNuxtPlugin({
  name: 'monitoring',
  enforce: 'pre',
  setup(nuxtApp) {
    const release = String(useRuntimeConfig().public.release)
    const reporting = createErrorReporting({
      readConfig: () => nuxtApp.$api.get('/api/client-config'),
      loadReporter: async (config) => {
        const { createReporter } = await import('~/services/monitoring/report')
        return createReporter({ dsn: config.errorReportingDsn, environment: config.environment, release, apiRelease: config.release, page: () => window.location.href })
      },
    })
    const keep = (mechanism: Mechanism) => (error: unknown): void => reporting.keep(error, mechanism)
    // The event itself when it has no error object: it still says where (file, line, column).
    const onError = (event: ErrorEvent): void => reporting.keep(event.error ?? event, 'onerror')
    const onRejection = (event: PromiseRejectionEvent): void => reporting.keep(event.reason, 'onunhandledrejection')
    window.addEventListener('error', onError)
    window.addEventListener('unhandledrejection', onRejection)
    nuxtApp.hook('vue:error', keep('vue'))
    // A plugin or the app failing to start; a page answered 404 is not a defect.
    nuxtApp.hook('app:error', (error) => {
      const status = (error as { statusCode?: number }).statusCode
      if (status === undefined || status >= 500) reporting.keep(error, 'nuxt')
    })
    // Nuxt drops its error handler once the app is up: errors of watchers and effects created
    // outside components would then only reach the console.
    nuxtApp.hook('app:suspense:resolve', () => {
      const previous = nuxtApp.vueApp.config.errorHandler
      nuxtApp.vueApp.config.errorHandler = (error, instance, info) => {
        reporting.keep(error, 'vue')
        if (previous) previous(error, instance, info)
        else console.error(error)
      }
    })

    const start = async (): Promise<void> => {
      const started = await reporting.start()
      if (started === 'retry') {
        // Offline at start: try again when the network is back (errors keep waiting meanwhile).
        window.addEventListener('online', () => void start(), { once: true })
        return
      }
      if (started === 'off') {
        // Nowhere to report: nothing more to listen to.
        window.removeEventListener('error', onError)
        window.removeEventListener('unhandledrejection', onRejection)
      }
    }
    nuxtApp.hook('app:mounted', () => void start())
  },
})
