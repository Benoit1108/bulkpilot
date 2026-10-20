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
  useTheme().setPreference('system')
  localStorage.clear()
})

const deviceTheme = () => (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')

describe('useTheme', () => {
  it('follows the device by default and the startup plugin reflects it on the document', () => {
    const { preference, theme } = useTheme()

    expect(preference.value).toBe('system')
    expect(theme.value).toBe(deviceTheme())
    expect(document.documentElement.dataset.theme).toBe(deviceTheme())
  })

  it('applies and remembers the light theme, including the browser theme colour', () => {
    const { theme, setPreference } = useTheme()

    setPreference('light')

    expect(theme.value).toBe('light')
    expect(document.documentElement.dataset.theme).toBe('light')
    expect(localStorage.getItem('theme')).toBe('light')
    expect(themeColor()).toBe('#f2f2f7')
  })

  it('remembers the dark theme as a choice of its own', () => {
    const { setPreference } = useTheme()

    setPreference('dark')

    expect(document.documentElement.dataset.theme).toBe('dark')
    expect(localStorage.getItem('theme')).toBe('dark')
    expect(themeColor()).toBe('#000000')
  })

  it('forgets the choice when going back to the device setting', () => {
    const { theme, setPreference } = useTheme()

    setPreference('light')
    setPreference('system')

    expect(localStorage.getItem('theme')).toBeNull()
    expect(theme.value).toBe(deviceTheme())
  })
})
