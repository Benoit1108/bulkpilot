import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it, vi } from 'vitest'
import Button from '~/components/ui/Button.vue'

describe('UiButton', () => {
  it('is a non-submitting button by default', async () => {
    const wrapper = await mountSuspended(Button, { slots: { default: () => 'Valider' } })

    expect(wrapper.get('button').attributes('type')).toBe('button')
    expect(wrapper.text()).toBe('Valider')
  })

  it('stays focusable but inert while loading', async () => {
    const onClick = vi.fn()
    const wrapper = await mountSuspended(Button, { props: { loading: true, onClick } })

    const button = wrapper.get('button')
    expect(button.attributes('disabled')).toBeUndefined()
    expect(button.attributes('aria-disabled')).toBe('true')
    expect(button.attributes('aria-busy')).toBe('true')
    await button.trigger('click')
    expect(onClick).not.toHaveBeenCalled()
  })
})
