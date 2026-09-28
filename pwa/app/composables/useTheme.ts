import { parsePreference, resolveTheme, THEME_COLORS, THEME_STORAGE_KEY, type Theme, type ThemePreference } from '~/features/shell/theme'

function readStoredPreference(): ThemePreference {
  try {
    return parsePreference(localStorage.getItem(THEME_STORAGE_KEY))
  }
  catch {
    return 'dark'
  }
}

function systemPrefersLight(): boolean {
  return typeof matchMedia === 'function' && matchMedia('(prefers-color-scheme: light)').matches
}

/**
 * Dark by default; "system" follows the OS. The preference is a per-device convenience kept in
 * localStorage (public/theme-init.js applies it before first paint); plugins/theme.client.ts
 * keeps the document in sync, including OS changes while "system" is selected.
 */
export function useTheme() {
  const preference = useState<ThemePreference>('theme-preference', readStoredPreference)
  const theme = useState<Theme>('theme', () => resolveTheme(preference.value, systemPrefersLight()))

  function apply(): void {
    theme.value = resolveTheme(preference.value, systemPrefersLight())
    document.documentElement.dataset.theme = theme.value
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', THEME_COLORS[theme.value])
  }

  function setPreference(next: ThemePreference): void {
    preference.value = next
    try {
      if (next === 'dark') localStorage.removeItem(THEME_STORAGE_KEY)
      else localStorage.setItem(THEME_STORAGE_KEY, next)
    }
    catch {
      // Storage unavailable: the choice still applies for this visit.
    }
    apply()
  }

  return { preference: readonly(preference), theme: readonly(theme), setPreference, apply }
}
