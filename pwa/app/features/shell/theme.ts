/**
 * Theme values shared by the Nuxt config (manifest, initial meta) and the runtime.
 * Keep in sync with assets/css/tokens.css and public/theme-init.js (plain JS, runs before the app).
 */
export type Theme = 'dark' | 'light'
export type ThemePreference = Theme | 'system'

export const THEME_STORAGE_KEY = 'theme'
export const THEME_COLORS: Readonly<Record<Theme, string>> = { dark: '#0d0b14', light: '#f7f6fb' }
export const BRAND_COLOR = '#6d28d9'

export function parsePreference(stored: string | null): ThemePreference {
  return stored === 'light' || stored === 'system' ? stored : 'dark'
}

export function resolveTheme(preference: ThemePreference, prefersLight: boolean): Theme {
  if (preference === 'system') return prefersLight ? 'light' : 'dark'
  return preference
}
