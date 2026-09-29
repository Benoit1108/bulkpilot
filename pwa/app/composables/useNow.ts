/**
 * The current time, refreshed every `every` ms while the component is mounted (timers, clocks).
 */
export function useNow(every: number) {
  const now = ref(Date.now())
  let timer: ReturnType<typeof setInterval> | undefined
  onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), every)
  })
  onBeforeUnmount(() => clearInterval(timer))
  return now
}
