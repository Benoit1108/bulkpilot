import type { Page } from '@playwright/test'

/** The smallest tap target on a touch screen (WCAG 2.5.5, AAA, and the design system's `min-h-tap`). */
export const TAP_PX = 44
/** The smallest target for any pointer (WCAG 2.5.8, AA): what the desktop widths are held to. */
export const POINTER_PX = 24

/**
 * What is wrong with the page's layout at its current size, as readable lines (empty: nothing).
 * Checked: no horizontal scroll; no text cut off by a container that hides its overflow;
 * interactive elements not overlapping each other, and none left under a fixed element (the « + »
 * button, the bottom navigation) once scrolled to the bottom; targets of at least 44 × 44 px on
 * touch screens, 24 × 24 px otherwise (a label counts with its control); the page title never
 * broken inside a word without a hyphen. With a dialog open (a drawer, the search, the « + »
 * panel), only what it holds is checked: the page behind is out of reach.
 *
 * Not checked: overlaps between fixed elements themselves (the « + » over the bottom navigation is
 * their design), and a sticky element at the top (there is none).
 *
 * Exempt from the tap size, as WCAG allows: links inside a sentence (`display: inline`), and an
 * element marked `data-tap-exempt` (with the reason next to it in the template).
 */
export async function layoutProblems(page: Page, touch: boolean): Promise<string[]> {
  const problems: string[] = []
  const check = (bottom: boolean): Promise<string[]> => page.evaluate(({ touch, tap, pointer, bottom }) => {
    const found: string[] = []
    const name = (element: Element) => {
      const label = element.getAttribute('aria-label') ?? (element as HTMLElement).innerText ?? ''
      return `<${element.tagName.toLowerCase()}> « ${label.trim().replace(/\s+/g, ' ').slice(0, 40)} »`
    }
    const fixed = (element: Element) => {
      for (let node: Element | null = element; node; node = node.parentElement) {
        const position = getComputedStyle(node).position
        if (position === 'fixed' || position === 'sticky') return true
      }
      return false
    }
    // What the user can see and press: visually hidden controls stand behind their label. Left out:
    // what is not rendered (a closed <details> keeps its boxes), and what sits off the page until
    // focused (the skip link).
    const scope = document.querySelector('dialog[open]') ?? document
    const controls = [...scope.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button], [role=option], [tabindex="0"]')]
      .map((element) => {
        const isHidden = element instanceof HTMLInputElement && element.getBoundingClientRect().width <= 2
        return isHidden ? element.closest('label') ?? element : element
      })
      .filter((element, index, all) => all.indexOf(element) === index)
      .filter((element) => {
        const rect = element.getBoundingClientRect()
        const onPage = rect.bottom + window.scrollY > 0 && rect.right + window.scrollX > 0
        return rect.width > 2 && rect.height > 2 && onPage && element.checkVisibility({ visibilityProperty: true, contentVisibilityAuto: true })
          && !element.closest('[inert], [aria-hidden=true]')
      })
    // A checkbox or a radio is pressed through its label as well: the target is both.
    const target = (element: Element): DOMRect => {
      const rect = element.getBoundingClientRect()
      const labels = element instanceof HTMLInputElement ? [...(element.labels ?? [])] : []
      return labels.reduce((union, label) => {
        const other = label.getBoundingClientRect()
        const [left, top] = [Math.min(union.left, other.left), Math.min(union.top, other.top)]
        return new DOMRect(left, top, Math.max(union.right, other.right) - left, Math.max(union.bottom, other.bottom) - top)
      }, rect)
    }

    if (!bottom && document.scrollingElement!.scrollWidth > window.innerWidth + 1) {
      found.push(`scrolls sideways: ${document.scrollingElement!.scrollWidth} px for ${window.innerWidth} px`)
    }

    // Text a container cuts off (it hides its overflow): an inner box too narrow for its words.
    // Left out: what scrolls on purpose (overflow auto/scroll) and the visually hidden (1 px).
    if (!bottom) {
      for (const element of scope.querySelectorAll<HTMLElement>('body *')) {
        const style = getComputedStyle(element)
        if (!['hidden', 'clip'].includes(style.overflowX) || element.clientWidth <= 2 || !element.innerText?.trim()) continue
        if (element.scrollWidth > element.clientWidth + 1 && element.checkVisibility()) found.push(`${name(element)} cuts its text off: ${element.scrollWidth} px for ${element.clientWidth} px`)
      }
    }

    const inFlow = controls.filter(element => !fixed(element))
    for (let i = 0; i < (bottom ? 0 : inFlow.length); i++) {
      for (let j = i + 1; j < inFlow.length; j++) {
        const [a, b] = [inFlow[i]!, inFlow[j]!]
        if (a.contains(b) || b.contains(a)) continue
        const [ra, rb] = [a.getBoundingClientRect(), b.getBoundingClientRect()]
        const width = Math.min(ra.right, rb.right) - Math.max(ra.left, rb.left)
        const height = Math.min(ra.bottom, rb.bottom) - Math.max(ra.top, rb.top)
        if (width > 2 && height > 2) found.push(`${name(a)} overlaps ${name(b)}`)
      }
    }

    // Once scrolled to the bottom, nothing left under the « + » or the bottom navigation: there is
    // no further scrolling to uncover it. (Higher up, content passes under them on its way.)
    for (const element of bottom ? inFlow : []) {
      const rect = element.getBoundingClientRect()
      const [x, y] = [rect.left + rect.width / 2, rect.top + rect.height / 2]
      if (y < window.innerHeight / 2 || y > window.innerHeight || x < 0 || x > window.innerWidth) continue
      const hit = document.elementFromPoint(x, y)
      if (hit && !element.contains(hit) && !hit.contains(element) && fixed(hit)) found.push(`${name(element)} is covered by ${name(hit)}`)
    }

    if (!bottom) {
      const least = touch ? tap : pointer
      for (const element of controls) {
        if (element.closest('[data-tap-exempt]') || getComputedStyle(element).display === 'inline') continue
        const rect = target(element)
        if (rect.width < least - 0.5 || rect.height < least - 0.5) found.push(`${name(element)} is ${Math.round(rect.width)} × ${Math.round(rect.height)} px`)
      }
    }

    const title = document.querySelector('h1')
    if (title && !bottom && getComputedStyle(title).hyphens !== 'auto') {
      const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT)
      for (let text = walker.nextNode(); text; text = walker.nextNode()) {
        for (const match of (text.textContent ?? '').matchAll(/\S+/g)) {
          const range = document.createRange()
          range.setStart(text, match.index)
          range.setEnd(text, match.index + match[0].length)
          const lines = new Set([...range.getClientRects()].map(rect => Math.round(rect.top)))
          if (lines.size > 1) found.push(`the title breaks « ${match[0]} » across lines`)
        }
      }
    }
    return found
  }, { touch, tap: TAP_PX, pointer: POINTER_PX, bottom })

  await page.evaluate(() => window.scrollTo(0, 0))
  problems.push(...await check(false))
  await page.evaluate(() => window.scrollTo(0, document.scrollingElement!.scrollHeight))
  problems.push(...await check(true))
  return [...new Set(problems)]
}

/**
 * Waits until the page has loaded what it shows: nothing still busy — a page stuck loading fails
 * (checking a skeleton would prove nothing). Requests in flight are only waited for a moment.
 */
export async function settled(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {})
  await page.locator('[aria-busy=true]').first().waitFor({ state: 'detached', timeout: 10_000 })
}

/**
 * WCAG 1.4.12 text spacing: the user's own line height, letter, word and paragraph spacing
 * (the success criterion's values), applied over the app's styles.
 */
export async function spaceText(page: Page): Promise<void> {
  // Through the CSSOM, as a user style sheet would (a <style> element is refused by the app's CSP).
  await page.evaluate(() => {
    const sheet = new CSSStyleSheet()
    sheet.replaceSync('* { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important } p { margin-bottom: 2em !important }')
    document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet]
  })
}
