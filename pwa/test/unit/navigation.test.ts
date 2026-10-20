import { describe, expect, it } from 'vitest'
import { activePage, isActiveSection, primaryNavigation } from '~/features/shell/navigation'

describe('primary navigation', () => {
  it('lists the five sections in bottom-nav order', () => {
    expect(primaryNavigation.map(item => item.label)).toEqual(['Accueil', 'Nutrition', 'Training', 'Progression', 'Coach'])
  })

  it.each([
    ['/', '/', true],
    ['/', '/training', false],
    ['/training', '/training', true],
    ['/training', '/training/session/1', true],
    ['/training', '/trainings', false],
  ])('section %s is active on %s: %s', (item, current, expected) => {
    expect(isActiveSection(item, current)).toBe(expected)
  })

  it.each([
    ['/nutrition', '/nutrition'],
    ['/nutrition/recettes/12', '/nutrition/recettes'],
    ['/nutrition/scanner', '/nutrition'],
    ['/nutritions', null],
  ])('on %s, the open page of Nutrition is %s', (current, expected) => {
    const nutrition = primaryNavigation.find(item => item.to === '/nutrition')!
    expect(activePage(nutrition.pages!, current)).toBe(expected)
  })

  it('marks the page a page reached from it belongs to', () => {
    const progression = primaryNavigation.find(item => item.to === '/progression')!
    expect(activePage(progression.pages!, '/progression/mesures')).toBe('/progression/mensurations')
  })

  it('opens each section on its own page first', () => {
    for (const item of primaryNavigation.filter(item => item.pages)) expect(item.pages![0]!.to).toBe(item.to)
  })
})
