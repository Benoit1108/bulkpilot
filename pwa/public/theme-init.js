// Runs before the app boots so the stored theme never flashes. Mirrors app/features/shell/theme.ts:
// "dark" is the default (nothing stored), "light" and "system" are the stored alternatives.
(function () {
  var colors = { dark: '#0d0b14', light: '#f7f6fb' }
  var theme = 'dark'
  try {
    var preference = localStorage.getItem('theme')
    if (preference === 'light') theme = 'light'
    else if (preference === 'system' && matchMedia('(prefers-color-scheme: light)').matches) theme = 'light'
  }
  catch {
    // Storage unavailable (private mode): keep the default theme.
  }
  document.documentElement.dataset.theme = theme

  // The theme-color meta may be declared after this script: set it once the head is parsed too.
  function applyThemeColor() {
    var meta = document.querySelector('meta[name="theme-color"]')
    if (meta) meta.setAttribute('content', colors[theme])
  }
  applyThemeColor()
  document.addEventListener('DOMContentLoaded', applyThemeColor)
})()
