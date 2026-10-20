// Runs before the app boots so the stored theme never flashes. Mirrors app/features/shell/theme.ts:
// the device's setting is the default (nothing stored), "light" and "dark" are the stored choices.
(function () {
  var colors = { dark: '#000000', light: '#f2f2f7' }
  var preference = null
  try {
    preference = localStorage.getItem('theme')
  }
  catch {
    // Storage unavailable (private mode): follow the device.
  }
  var theme = preference === 'light' || preference === 'dark'
    ? preference
    : (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
  document.documentElement.dataset.theme = theme

  // The theme-color meta may be declared after this script: set it once the head is parsed too.
  function applyThemeColor() {
    var meta = document.querySelector('meta[name="theme-color"]')
    if (meta) meta.setAttribute('content', colors[theme])
  }
  applyThemeColor()
  document.addEventListener('DOMContentLoaded', applyThemeColor)
})()
