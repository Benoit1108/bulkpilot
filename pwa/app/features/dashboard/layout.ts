import { DASHBOARD_KEYS, type Widget } from './dashboard'

/**
 * The account's dashboard layout (spec §82: remembered on every device): the widgets in order,
 * each shown or hidden. The server gives the default one until the user arranges it.
 */
export function useDashboardLayout() {
  const api = useApi()
  const { data, status, error, refresh } = useAsyncData(DASHBOARD_KEYS.layout, () => api.get('/api/me/dashboard'))

  async function save(widgets: Widget[]): Promise<void> {
    data.value = await api.put('/api/me/dashboard', { widgets: widgets.map(({ key, visible }) => ({ key, visible })) })
  }

  return { layout: data, status, error, refresh, save }
}
