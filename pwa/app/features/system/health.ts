import type { ApiClient } from '~/services/api/client'
import { asApiError } from '~/services/api/client'
import type { components } from '~/types/api'

export type Health = components['schemas']['Health']
export type ServiceStatus = 'checking' | 'ok' | 'degraded' | 'unreachable'

export function fetchHealth(api: ApiClient): Promise<Health> {
  return api.get('/api/health')
}

/**
 * Server reachability, shared by the connectivity banner and the settings page
 * (same async-data key: one request for both).
 */
export function useServiceHealth() {
  const api = useApi()
  const { data, error, status, refresh } = useAsyncData('system-health', () => fetchHealth(api))

  const apiError = computed(() => (error.value ? asApiError(error.value) : null))
  const serviceStatus = computed<ServiceStatus>(() => {
    if (status.value === 'pending' || status.value === 'idle') return 'checking'
    if (apiError.value) return apiError.value.status === 503 ? 'degraded' : 'unreachable'
    return data.value?.status === 'ok' ? 'ok' : 'degraded'
  })

  return { serviceStatus, apiError, refresh }
}
