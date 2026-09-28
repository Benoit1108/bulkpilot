// Keeps the document (data-theme, theme-color meta) in sync with the theme preference.
export default defineNuxtPlugin(() => {
  const { preference, apply } = useTheme()
  apply()

  matchMedia('(prefers-color-scheme: light)').addEventListener('change', () => {
    if (preference.value === 'system') apply()
  })
})
