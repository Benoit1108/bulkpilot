import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Nav from '~/components/shell/Nav.vue'

describe('ShellNav', () => {
  it('lists the five sections and settings, marking only the current one', async () => {
    const wrapper = await mountSuspended(Nav, { route: '/training' })

    const links = wrapper.findAll('a')
    expect(links.map(link => link.text())).toEqual(['Accueil', 'Nutrition', 'Training', 'Progression', 'Coach', 'Réglages'])
    expect(links.filter(link => link.attributes('aria-current') === 'page').map(link => link.text())).toEqual(['Training'])
  })

  it('is exposed as the main navigation landmark', async () => {
    const wrapper = await mountSuspended(Nav, { route: '/' })

    expect(wrapper.get('nav').attributes('aria-label')).toBe('Navigation principale')
  })
})
