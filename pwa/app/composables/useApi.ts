import type { ApiClient } from '~/services/api/client'

export function useApi(): ApiClient {
  return useNuxtApp().$api
}
