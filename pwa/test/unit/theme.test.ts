import { describe, expect, it } from 'vitest'
import { parsePreference, resolveTheme } from '~/features/shell/theme'

describe('theme preference', () => {
  it.each([
    [null, 'dark'],
    ['light', 'light'],
    ['system', 'system'],
    ['dark', 'dark'],
    ['garbage', 'dark'],
  ])('parses stored %s as %s', (stored, expected) => {
    expect(parsePreference(stored)).toBe(expected)
  })

  it('resolves "system" from the OS and keeps explicit choices', () => {
    expect(resolveTheme('system', true)).toBe('light')
    expect(resolveTheme('system', false)).toBe('dark')
    expect(resolveTheme('light', false)).toBe('light')
    expect(resolveTheme('dark', true)).toBe('dark')
  })
})
