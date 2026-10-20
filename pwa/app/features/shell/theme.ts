/**
 * Theme values shared by the Nuxt config (manifest, initial meta) and the runtime.
 * Keep in sync with assets/css/tokens.css and public/theme-init.js (plain JS, runs before the app).
 */
export type Theme = 'dark' | 'light'
export type ThemePreference = Theme | 'system'

export const THEME_STORAGE_KEY = 'theme'
export const THEME_COLORS: Readonly<Record<Theme, string>> = { dark: '#000000', light: '#f2f2f7' }

/** Nothing chosen yet: the device's setting (light or dark) decides. */
export function parsePreference(stored: string | null): ThemePreference {
  return stored === 'light' || stored === 'dark' ? stored : 'system'
}

export function resolveTheme(preference: ThemePreference, prefersLight: boolean): Theme {
  if (preference === 'system') return prefersLight ? 'light' : 'dark'
  return preference
}
