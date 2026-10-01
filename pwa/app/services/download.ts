/**
 * Saves a file from a (signed) link without leaving the app. A navigation would stop it — Nuxt's
 * external `navigateTo` tears the app down before setting the location, and the page stays since
 * the file comes as an attachment — whereas a link clicked with `download` lets the browser save
 * it and leaves the page as it was.
 */
export function download(url: string): void {
  const link = document.createElement('a')
  link.href = url
  link.download = ''
  link.rel = 'noopener'
  link.hidden = true
  document.body.append(link)
  link.click()
  link.remove()
}
