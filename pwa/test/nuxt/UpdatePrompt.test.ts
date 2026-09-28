import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import UpdatePrompt from '~/components/shell/UpdatePrompt.vue'

const { needRefresh, applyUpdate } = vi.hoisted(() => ({ needRefresh: { value: false }, applyUpdate: vi.fn() }))

mockNuxtImport('usePwaUpdate', () => () => ({ needRefresh: toRef(needRefresh, 'value'), applyUpdate }))

describe('UpdatePrompt', () => {
  it('stays silent until a new version is waiting, then lets the user choose', async () => {
    const wrapper = await mountSuspended(UpdatePrompt)
    expect(wrapper.get('[role="status"]').text()).toBe('')

    needRefresh.value = true
    const refreshed = await mountSuspended(UpdatePrompt)
    expect(refreshed.text()).toContain('Une nouvelle version est disponible.')

    await refreshed.findAll('button').find(button => button.text() === 'Mettre à jour')!.trigger('click')
    expect(applyUpdate).toHaveBeenCalledOnce()

    await refreshed.findAll('button').find(button => button.text() === 'Plus tard')!.trigger('click')
    expect(refreshed.get('[role="status"]').text()).toBe('')
  })
})
