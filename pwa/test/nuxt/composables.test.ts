import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { useEventListener } from '~/composables/useEventListener'
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

describe('usePwaUpdate', () => {
  it('reports nothing to update and ignores apply when no service worker is registered', () => {
    const { needRefresh, applyUpdate } = usePwaUpdate()

    expect(needRefresh.value).toBe(false)
    expect(() => applyUpdate()).not.toThrow()
  })
})
