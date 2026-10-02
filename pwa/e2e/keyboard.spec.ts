import { expect, test } from './support/fixtures'
import { populate } from './support/seed'

/**
 * WCAG 2.4.11 (focus not obscured): on a phone the bottom navigation, the « + » button and the
 * toasts stay over the page. Walking a long page with the keyboard (an external keyboard, switch
 * access), every control reached is scrolled clear of them: at its centre, the page shows it.
 */
test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true })

test('a control reached with the keyboard is never hidden under the bottom navigation', async ({ page, account: _account }) => {
  test.skip(test.info().project.name !== 'mobile', 'the phone layout: once, on the mobile project')
  test.setTimeout(90_000)
  await populate(page)
  for (const path of ['/', '/nutrition', '/reglages']) {
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    const hidden: string[] = []
    for (let step = 0; step < 60; step++) {
      await page.keyboard.press('Tab')
      const covered = await page.evaluate(() => {
        const focused = document.activeElement
        if (!(focused instanceof HTMLElement) || focused === document.body) return null
        // Inside the fixed bars themselves (the navigation, the « + »): nothing can cover them.
        for (let node: HTMLElement | null = focused; node; node = node.parentElement) {
          if (getComputedStyle(node).position === 'fixed') return null
        }
        const rect = focused.getBoundingClientRect()
        const hit = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2)
        return hit && (focused.contains(hit) || hit.contains(focused)) ? null : `${focused.tagName.toLowerCase()} « ${(focused.getAttribute('aria-label') ?? focused.innerText).trim().slice(0, 40)} » under ${hit?.tagName.toLowerCase()}`
      })
      if (covered) hidden.push(`${path}: ${covered}`)
    }
    expect(hidden).toEqual([])
  }
})
