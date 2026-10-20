import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { useEventListener } from '~/composables/useEventListener'
import { PAGE_HEADING_KEY, usePageHeading } from '~/composables/usePageHeading'
import { usePwaUpdate } from '~/composables/usePwaUpdate'

describe('useEventListener', () => {
  it('listens while the component is mounted only', async () => {
    const listener = vi.fn()
    const wrapper = await mountSuspended(defineComponent({
      setup() {
        useEventListener('online', listener)
        return () => h('div')
      },
    }))

    window.dispatchEvent(new Event('online'))
    wrapper.unmount()
    window.dispatchEvent(new Event('online'))

    expect(listener).toHaveBeenCalledOnce()
  })
})

describe('usePageHeading', () => {
  const page = (heading: string) => defineComponent({
    setup() {
      usePageHeading(() => heading)
      return () => h('div')
    },
  })

  it('keeps the heading of the page that comes next, cleared once that one leaves', async () => {
    const before = await mountSuspended(page('Squat'))
    const next = await mountSuspended(page('Push'))
    before.unmount()
    expect(useState(PAGE_HEADING_KEY).value, 'the next page set it before this one left').toBe('Push')

    next.unmount()
    expect(useState(PAGE_HEADING_KEY).value).toBeNull()
  })
})

describe('usePwaUpdate', () => {
  it('reports nothing to update and ignores apply when no service worker is registered', () => {
    const { needRefresh, applyUpdate } = usePwaUpdate()

    expect(needRefresh.value).toBe(false)
    expect(() => applyUpdate()).not.toThrow()
  })
})
