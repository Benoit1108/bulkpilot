import { createApiClient, type ApiClient } from '~/services/api/client'

let client: ApiClient | null = null

export function useApi(): ApiClient {
  if (client === null) {
    client = createApiClient(useRuntimeConfig().public.apiBase)
  }

  return client
}
