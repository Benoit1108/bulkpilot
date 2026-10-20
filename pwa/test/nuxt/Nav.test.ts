import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import Nav from '~/components/shell/Nav.vue'

describe('ShellNav', () => {
  it('lists the five sections and settings, the open section with its pages, one current page', async () => {
    const wrapper = await mountSuspended(Nav, { route: '/training/exercices/42' })

    const links = wrapper.findAll('a')
    expect(links.map(link => link.text())).toEqual(['Accueil', 'Nutrition', 'Training', 'Aujourd’hui', 'Exercices', 'Historique', 'Programmes', 'Progression', 'Coach', 'Réglages'])
    expect(links.filter(link => link.attributes('aria-current')).map(link => [link.text(), link.attributes('aria-current')])).toEqual([['Training', 'true'], ['Exercices', 'page']])
  })

  it('marks the own page of the section under it, the section itself as the current section', async () => {
    const wrapper = await mountSuspended(Nav, { route: '/training' })

    expect(wrapper.findAll('a[aria-current]').map(link => [link.text(), link.attributes('aria-current')])).toEqual([['Training', 'true'], ['Aujourd’hui', 'page']])
  })

  it('makes a section without pages the current page itself', async () => {
    const wrapper = await mountSuspended(Nav, { route: '/' })

    expect(wrapper.findAll('a[aria-current]').map(link => [link.text(), link.attributes('aria-current')])).toEqual([['Accueil', 'page']])
  })

  it('is exposed as the main navigation landmark', async () => {
    const wrapper = await mountSuspended(Nav, { route: '/' })

    expect(wrapper.get('nav').attributes('aria-label')).toBe('Navigation principale')
  })
})
