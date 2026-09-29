import { localDay } from '~/services/calendar/days'

/**
 * The user's today (YYYY-MM-DD), kept current: an installed PWA can stay open past midnight, so it
 * is read again whenever the app comes back to the foreground.
 */
export function useToday(): Readonly<Ref<string>> {
  const today = ref(localDay())
  function refresh(): void {
    if (document.visibilityState === 'visible') today.value = localDay()
  }
  onMounted(() => document.addEventListener('visibilitychange', refresh))
  onBeforeUnmount(() => document.removeEventListener('visibilitychange', refresh))
  return readonly(today)
}
