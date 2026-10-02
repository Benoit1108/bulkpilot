import AxeBuilder from '@axe-core/playwright'
import type { Page } from '@playwright/test'
import { expect } from './fixtures'

/** WCAG 2.2 AA violations on the page as it is now (drawers included), as readable lines. */
export async function accessibilityProblems(page: Page): Promise<string[]> {
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze()
  return violations.map(v => `${v.id}: ${v.nodes.map(n => n.target.join(' ')).join(', ')}`)
}

/** No WCAG 2.2 AA violation on the page as it is now (drawers included). */
export async function expectAccessible(page: Page): Promise<void> {
  expect(await accessibilityProblems(page)).toEqual([])
}

/** The light theme, as the user would choose it (public/theme-init.js reads it before first paint). */
export async function useLightTheme(page: Page): Promise<void> {
  await page.evaluate(() => localStorage.setItem('theme', 'light'))
  await page.reload()
}
