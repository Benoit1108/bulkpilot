import { counted } from '~/services/text/plural'

interface Paged {
  id: string
  createdAt: string
}

/**
 * A list read a page at a time, the latest first (the Coach history, the notifications): the
 * first page is cached under `key`, « Voir plus » asks for the next one with the time and the id
 * of the last item shown (several share a second). Focus goes to the first item added, or to the
 * list when there was none left; either way it is announced.
 */
export function usePagedList<T extends Paged>(
  key: string,
  fetchPage: (cursor: { before?: string, beforeId?: string }) => Promise<T[]>,
  options: { size: number, noun: [one: string, many?: string] },
) {
  const { data: firstPage, status, error, refresh } = useAsyncData(key, () => fetchPage({}))
  const more = ref<T[]>([]) as Ref<T[]>
  const items = computed(() => [...(firstPage.value ?? []), ...more.value])
  const hasMore = ref(true)
  // A page asked for before the list was read again belongs to the old list: it is dropped.
  let version = 0
  watch(firstPage, (page) => {
    version++
    more.value = []
    hasMore.value = (page?.length ?? 0) === options.size
  }, { immediate: true })

  const loading = useSubmission()
  const notice = ref<string | null>(null)
  const list = ref<HTMLElement | null>(null)
  // By id: the order of a v-for ref array is not the list's.
  const elements = new Map<string, HTMLElement>()
  function keep(id: string, element: unknown): void {
    if (element instanceof HTMLElement) elements.set(id, element)
    else elements.delete(id)
  }

  async function loadMore(): Promise<void> {
    const last = items.value.at(-1)
    const asked = version
    let added: T[] | null = null
    await loading.run(async () => {
      const page = await fetchPage({ before: last?.createdAt, beforeId: last?.id })
      if (asked !== version) return
      more.value.push(...page)
      hasMore.value = page.length === options.size
      added = page
    })
    if (added === null) return
    const [one, many] = options.noun
    const first = (added as T[])[0]
    notice.value = first ? `${counted((added as T[]).length, one, many)} de plus.` : `Plus d’autres ${many ?? `${one}s`}.`
    await nextTick()
    // The button may be gone (last page): focus goes to the first one added, else to the list.
    ;(first ? elements.get(first.id) : list.value)?.focus()
  }

  /** The item changed in place, in whichever page it is (no reload of the pages already there). */
  function replace(changed: T): void {
    const swap = (item: T) => (item.id === changed.id ? changed : item)
    // The first page is a shallow ref: it is replaced, not changed in place.
    if (firstPage.value) firstPage.value = firstPage.value.map(swap)
    more.value = more.value.map(swap)
  }

  /** Every item changed the same way (« Tout marquer comme lu »). */
  function updateAll(change: (item: T) => T): void {
    if (firstPage.value) firstPage.value = firstPage.value.map(change)
    more.value = more.value.map(change)
  }

  return { firstPage, items, status, error, refresh, hasMore, loading, notice, list, keep, loadMore, replace, updateAll, element: (id: string) => elements.get(id) }
}
