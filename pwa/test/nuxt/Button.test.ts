import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Button from '~/components/ui/Button.vue'

describe('UiButton', () => {
  it('is a non-submitting button by default', async () => {
    const wrapper = await mountSuspended(Button, { slots: { default: () => 'Valider' } })

    expect(wrapper.get('button').attributes('type')).toBe('button')
    expect(wrapper.text()).toBe('Valider')
  })

  it('is disabled and busy while loading', async () => {
    const wrapper = await mountSuspended(Button, { props: { loading: true } })

    const button = wrapper.get('button')
    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-busy')).toBe('true')
  })
})
