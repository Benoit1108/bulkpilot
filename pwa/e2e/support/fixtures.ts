import { test as base, expect, type Browser, type BrowserContext, type Page } from '@playwright/test'

export const PASSWORD = 'correct horse battery'

export interface TestAccount {
  email: string
  firstName: string
}

/** A separate device (cookies, storage) pointed at the stack under test. */
export function newDevice(browser: Browser, userAgent?: string): Promise<BrowserContext> {
  return browser.newContext({ baseURL: process.env.E2E_BASE_URL ?? 'https://localhost', ignoreHTTPSErrors: true, userAgent })
}

export function uniqueEmail(prefix = 'e2e'): string {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.com`
}

/** Registers through the API: the page's browser context is then signed in. */
export async function registerThroughApi(page: Page, account: TestAccount): Promise<void> {
  const response = await page.request.post('/api/auth/register', {
    data: { ...account, password: PASSWORD },
    headers: { Accept: 'application/json' },
  })
  expect(response.status(), await response.text()).toBe(201)
}

/**
 * `account`: a fresh account, signed in in this test's browser context.
 */
export const test = base.extend<{ account: TestAccount }>({
  account: async ({ page }, use) => {
    const account = { email: uniqueEmail(), firstName: 'Alex' }
    await registerThroughApi(page, account)
    await use(account)
  },
})

export { expect }

/** Every `page` is already signed in to a fresh account. */
export const signedInTest = base.extend({
  page: async ({ page }, use) => {
    await registerThroughApi(page, { email: uniqueEmail(), firstName: 'Alex' })
    await use(page)
  },
})
