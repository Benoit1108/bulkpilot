import { afterEach, beforeAll, describe, expect, it } from 'vitest'
import { useTheme } from '~/composables/useTheme'

function themeColor(): string | null {
  return document.querySelector('meta[name="theme-color"]')?.getAttribute('content') ?? null
}

beforeAll(() => {
  // Rendered by the real index.html; absent from the test DOM.
  const meta = document.createElement('meta')
  meta.name = 'theme-color'
  document.head.append(meta)
})

afterEach(() => {
  useTheme().setPreference('dark')
  localStorage.clear()
})

describe('useTheme', () => {
  it('is dark by default and the startup plugin reflects it on the document', () => {
    const { preference, theme } = useTheme()

    expect(preference.value).toBe('dark')
    expect(theme.value).toBe('dark')
    expect(document.documentElement.dataset.theme).toBe('dark')
  })

  it('applies and remembers the light theme, including the browser theme colour', () => {
    const { theme, setPreference } = useTheme()

    setPreference('light')

    expect(theme.value).toBe('light')
    expect(document.documentElement.dataset.theme).toBe('light')
    expect(localStorage.getItem('theme')).toBe('light')
    expect(themeColor()).toBe('#f7f6fb')
  })

  it('forgets the preference when going back to the dark default', () => {
    const { setPreference } = useTheme()

    setPreference('light')
    setPreference('dark')

    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(localStorage.getItem('theme')).toBeNull()
    expect(themeColor()).toBe('#0d0b14')
  })

  it('stores "system" and resolves it from the OS preference', () => {
    const { theme, setPreference } = useTheme()

    setPreference('system')

    expect(localStorage.getItem('theme')).toBe('system')
    expect(theme.value).toBe(matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
  })
})
