import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent } from 'vue'

function stubWakeLock() {
  const release = vi.fn(() => Promise.resolve())
  const request = vi.fn(() => Promise.resolve({ release }))
  vi.stubGlobal('navigator', { ...navigator, wakeLock: { request } })
  return { request, release }
}

const Screen = defineComponent({
  setup() {
    const { keepScreenOn } = useWakeLock()
    return { keepScreenOn }
  },
  template: '<p>Séance</p>',
})

describe('useWakeLock', () => {
  it('keeps the screen on, asks again on return to the page, and lets go when unmounted', async () => {
    const { request, release } = stubWakeLock()
    const wrapper = await mountSuspended(Screen)
    expect(request, 'nothing before it is asked for').not.toHaveBeenCalled()

    wrapper.vm.keepScreenOn()
    await flushPromises()
    expect(request).toHaveBeenCalledWith('screen')

    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()
    expect(request).toHaveBeenCalledTimes(2)

    wrapper.unmount()
    await flushPromises()
    expect(release).toHaveBeenCalledOnce()
    document.dispatchEvent(new Event('visibilitychange'))
    expect(request, 'no more once gone').toHaveBeenCalledTimes(2)
  })

  it('releases a lock granted after the screen was left', async () => {
    const { release } = stubWakeLock()
    const wrapper = await mountSuspended(Screen)
    wrapper.vm.keepScreenOn()
    wrapper.unmount()
    await flushPromises()

    expect(release).toHaveBeenCalledOnce()
  })

  it('does without where the browser refuses or lacks it', async () => {
    vi.stubGlobal('navigator', { ...navigator, wakeLock: { request: () => Promise.reject(new Error('NotAllowedError')) } })
    const wrapper = await mountSuspended(Screen)

    expect(() => wrapper.vm.keepScreenOn()).not.toThrow()
    await flushPromises()
    wrapper.unmount()
  })
})
