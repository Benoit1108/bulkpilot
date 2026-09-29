import AxeBuilder from '@axe-core/playwright'
import type { Page } from '@playwright/test'
import { expect } from './fixtures'

/** WCAG 2.2 AA violations on the page as it is now (drawers included). */
export async function expectAccessible(page: Page): Promise<void> {
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze()
  expect(violations.map(v => `${v.id}: ${v.nodes.map(n => n.target.join(' ')).join(', ')}`)).toEqual([])
}
