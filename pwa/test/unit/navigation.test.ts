import { describe, expect, it } from 'vitest'
import { isActiveSection, primaryNavigation } from '~/features/shell/navigation'

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
})
