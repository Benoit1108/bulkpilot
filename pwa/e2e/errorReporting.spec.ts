import { expect, test } from './support/fixtures'

/**
 * Error reporting on the production image (docs/MONITORING.md): the test stack declares a DSN on a
 * host that does not exist (PWA_SENTRY_DSN=https://key@errors.test/1); the report is intercepted
 * before it leaves. What it may say: where and how it broke, never who, never a query string.
 */
test.describe('error reporting', () => {
  test('a crash sends one scrubbed report to the DSN host, which the CSP allows without the key', async ({ page }) => {
    const config = await (await page.request.get('/api/client-config', { headers: { Accept: 'application/json' } })).json() as { errorReportingDsn: string | null }
    test.skip(config.errorReportingDsn === null, 'this stack reports no error (PWA_SENTRY_DSN empty)')
    const dsn = new URL(config.errorReportingDsn!)

    const policy = (await page.request.get('/')).headers()['content-security-policy'] ?? ''
    expect(policy).toMatch(new RegExp(`connect-src[^;]*${dsn.origin.replace(/\./gu, '\\.')}`, 'u'))
    expect(policy, 'the DSN key is not in the policy').not.toContain(`${dsn.username}@`)

    const envelopes: string[] = []
    await page.route(`${dsn.origin}/**`, async (route) => {
      envelopes.push(route.request().postData() ?? '')
      await route.fulfill({ status: 200, body: '{}' })
    })
    await page.goto('/connexion?email=alex@example.com')
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    // An error nothing catches, twice: one report.
    await page.evaluate(() => {
      for (let i = 0; i < 2; i++) {
        setTimeout(() => {
          throw new Error('Boom for alex@example.com')
        })
      }
    })

    await expect.poll(() => envelopes.length).toBe(1)
    const event = JSON.parse(envelopes[0]!.split('\n')[2]!) as { request: { url: string }, exception: { values: Array<{ value: string, mechanism: { type: string } }> }, user?: unknown }
    expect(event.exception.values[0]?.value).toBe('Boom for [email]')
    expect(event.exception.values[0]?.mechanism.type).toBe('onerror')
    expect(event.request.url, 'no query string').not.toContain('?')
    expect(event.user).toBeUndefined()
  })
})
