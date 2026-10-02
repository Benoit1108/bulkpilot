import { expect, PASSWORD, test, uniqueEmail } from './support/fixtures'

/**
 * What the production image tells browsers (spec §101), checked where it is set: Caddy and
 * Symfony in front of the built app, over TLS.
 */
test.describe('security headers', () => {
  test('the app page may only load itself, is framed nowhere and is served over HTTPS only', async ({ page }) => {
    const response = await page.request.get('/')
    const headers = response.headers()

    const policy = headers['content-security-policy'] ?? ''
    for (const directive of ['default-src \'self\'', 'frame-ancestors \'none\'', 'object-src \'none\'', 'base-uri \'self\'', 'form-action \'self\'']) {
      expect(policy).toContain(directive)
    }
    expect(policy, 'no inline script runs unless built with the app (by hash)').not.toMatch(/script-src[^;]*'unsafe-inline'/)
    expect(policy, 'no inline style either').not.toMatch(/style-src[^;]*'unsafe-inline'/)
    expect(headers['strict-transport-security']).toMatch(/max-age=\d{7,}/)
    expect(headers['x-content-type-options']).toBe('nosniff')
    expect(headers['x-frame-options']).toBe('DENY')
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin')
    expect(headers['permissions-policy']).toContain('geolocation=()')
    expect(headers['cross-origin-opener-policy']).toBe('same-origin')
    expect(headers.server, 'the server does not name itself').toBeUndefined()
  })

  test('the API answers data that may load nothing', async ({ page }) => {
    const response = await page.request.get('/api/health', { headers: { Accept: 'application/json' } })
    const headers = response.headers()

    expect(headers['content-security-policy']).toBe('default-src \'none\'; frame-ancestors \'none\'; base-uri \'none\'; form-action \'none\'')
    expect(headers['x-content-type-options']).toBe('nosniff')
    expect(headers['strict-transport-security']).toMatch(/max-age=\d{7,}/)
    expect(headers['x-powered-by'], 'PHP does not name itself').toBeUndefined()

    const refused = await page.request.get('/api/me', { headers: { Accept: 'application/json' } })
    expect(refused.status()).toBe(401)
    expect(refused.headers()['content-security-policy'], 'errors too').toBe(headers['content-security-policy'])
  })

  test('the session cookie is out of reach of scripts and other sites', async ({ page }) => {
    const email = uniqueEmail('cookie')
    const response = await page.request.post('/api/auth/register', { data: { firstName: 'Alex', email, password: PASSWORD }, headers: { Accept: 'application/json' } })
    expect(response.status()).toBe(201)

    const cookies = response.headersArray().filter(header => header.name.toLowerCase() === 'set-cookie').map(header => header.value)
    expect(cookies.length).toBeGreaterThan(0)
    for (const cookie of cookies) {
      expect(cookie).toMatch(/;\s*secure/i)
      expect(cookie).toMatch(/;\s*httponly/i)
      expect(cookie).toMatch(/;\s*samesite=lax/i)
    }
  })

  test('errors never show a stack trace', async ({ page, account: _account }) => {
    const response = await page.request.post('/api/nutrition/entries', { data: '{not json', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } })

    expect(response.status()).toBe(400)
    const body = await response.text()
    expect(body).not.toContain('trace')
    expect(body).not.toContain('/app/')
  })

  test('the barcode reader can still compile its WebAssembly (the fallback without BarcodeDetector)', async ({ page, account: _account }) => {
    // zxing-wasm reads barcodes where the browser has no BarcodeDetector (iOS Safari, Firefox):
    // compiling WebAssembly needs 'wasm-unsafe-eval' in script-src. The smallest valid module
    // tells whether this page's policy allows it, without a camera.
    await page.goto('/nutrition')
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    const compiled = await page.evaluate(() => WebAssembly.compile(new Uint8Array([0x00, 0x61, 0x73, 0x6D, 0x01, 0x00, 0x00, 0x00])).then(() => 'compiled', (error: unknown) => String(error)))
    expect(compiled).toBe('compiled')
  })

  test('using the app breaks none of its own policy', async ({ page, account: _account }) => {
    const violations: string[] = []
    await page.exposeFunction('reportViolation', (violation: string) => violations.push(violation))
    await page.addInitScript(() => {
      document.addEventListener('securitypolicyviolation', (event) => {
        (window as unknown as { reportViolation: (violation: string) => void }).reportViolation(`${event.violatedDirective} ${event.blockedURI}`)
      })
    })

    for (const path of ['/', '/nutrition', '/training', '/progression', '/progression/photos', '/coach', '/reglages']) {
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    }
    await page.keyboard.press('Control+k')
    await expect(page.getByRole('combobox', { name: 'Rechercher' })).toBeFocused()

    expect(violations).toEqual([])
  })
})
