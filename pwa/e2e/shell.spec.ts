import { expect, signedInTest as test } from './support/fixtures'

const sections = ['Accueil', 'Nutrition', 'Training', 'Progression', 'Coach'] as const
/** The home page greets the user in its heading; the others are titled after their section. */
const heading = (section: string) => (section === 'Accueil' ? /^Bonjour/ : section)

test.describe('app shell', () => {
  test('bottom navigation or rail reaches every section, with a matching document title', async ({ page }) => {
    await page.goto('/')
    const nav = page.getByRole('navigation', { name: 'Navigation principale' })

    for (const section of [...sections.slice(1), 'Accueil']) {
      await nav.getByRole('link', { name: section, exact: true }).click()
      await expect(page.getByRole('heading', { level: 1, name: heading(section) })).toBeVisible()
      // A section with pages is the current section; its own page is the current page (sidebar).
      await expect(nav.getByRole('link', { name: section, exact: true })).toHaveAttribute('aria-current', section === 'Accueil' ? 'page' : 'true')
      await expect(page).toHaveTitle(`${section} · BulkPilot`)
    }
  })

  test('deep links are served by the SPA fallback', async ({ page }) => {
    await page.goto('/training')

    await expect(page.getByRole('heading', { level: 1, name: 'Training' })).toBeVisible()
  })

  test('unknown pages show a French error page with a way back', async ({ page }) => {
    await page.goto('/nope')

    await expect(page.getByRole('heading', { name: 'Cette page n’existe pas.' })).toBeVisible()
    await page.getByRole('button', { name: 'Retour à l’accueil' }).click()
    await expect(page.getByRole('heading', { level: 1, name: heading('Accueil') })).toBeVisible()
  })
})

test.describe('settings', () => {
  test('are reachable from every screen size and report the server status', async ({ page }) => {
    await page.goto('/')
    await page.getByRole('link', { name: 'Réglages' }).click()

    await expect(page.getByRole('heading', { level: 1, name: 'Réglages' })).toBeVisible()
    await expect(page.getByTestId('service-status')).toHaveText('Connecté')
  })

  test('theme follows the device by default and the chosen theme survives a reload', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' })
    await page.goto('/reglages')
    const html = page.locator('html')
    await expect(html).toHaveAttribute('data-theme', 'dark')
    await expect(page.getByRole('radio', { name: 'Système' })).toBeChecked()
    await page.emulateMedia({ colorScheme: 'light' })
    await expect(html, 'a change of the device setting applies at once').toHaveAttribute('data-theme', 'light')
    await page.emulateMedia({ colorScheme: 'dark' })

    await page.getByRole('radio', { name: 'Clair' }).check()
    await expect(html).toHaveAttribute('data-theme', 'light')

    await page.reload()
    await expect(html).toHaveAttribute('data-theme', 'light')
    await expect(page.getByRole('radio', { name: 'Clair' })).toBeChecked()
  })

  test('long pages scroll in both themes', async ({ page }) => {
    for (const theme of ['Sombre', 'Clair']) {
      await page.goto('/reglages')
      await page.getByRole('radio', { name: theme }).check()
      await page.goto('/reglages/compte')
      await page.getByRole('button', { name: 'Déconnecter', exact: false }).or(page.getByText('Cet appareil')).first().scrollIntoViewIfNeeded()
      await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
      expect(await page.evaluate(() => window.scrollY), theme).toBeGreaterThan(0)
    }
  })

  for (const scheme of ['light', 'dark'] as const) {
    test(`with nothing stored, the device's ${scheme} theme is applied before the app bundle runs`, async ({ page }) => {
      await page.emulateMedia({ colorScheme: scheme })
      await page.route('**/_nuxt/**', route => route.abort())

      await page.goto('/')

      await expect(page.locator('html')).toHaveAttribute('data-theme', scheme)
      await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', scheme === 'light' ? '#f2f2f7' : '#000000')
    })
  }

  test('the stored theme is applied before the app bundle runs', async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('theme', 'light'))
    await page.route('**/_nuxt/**', route => route.abort())

    await page.goto('/')

    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
    await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#f2f2f7')
  })
})

test.describe('layout by screen size', () => {
  test('phones: the navigation bar sits at the bottom and the page ends above it', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'phone layout only')
    await page.goto('/reglages/compte')
    const nav = (await page.getByRole('navigation', { name: 'Navigation principale' }).boundingBox())!
    const viewport = page.viewportSize()!
    expect(nav.y + nav.height).toBe(viewport.height)

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight))
    const main = (await page.locator('main').boundingBox())!
    expect(main.y + main.height, 'the last content is not under the bar').toBeLessThanOrEqual(nav.y + 1)
  })

  test('tablets: a rail on the left, the page beside it', async ({ page, isMobile }) => {
    test.skip(isMobile, 'resized from the desktop project')
    await page.setViewportSize({ width: 900, height: 800 })
    await page.goto('/')

    const nav = (await page.getByRole('navigation', { name: 'Navigation principale' }).boundingBox())!
    expect([nav.x, nav.y, nav.width, nav.height]).toEqual([0, 0, 88, 800])
    const main = (await page.locator('main').boundingBox())!
    expect(main.x).toBeGreaterThanOrEqual(nav.width)
    await expect(page.getByRole('navigation', { name: 'Navigation principale' }).getByText('Nutrition')).toBeVisible()
  })

  test('each theme paints its own background', async ({ page }) => {
    await page.goto('/reglages')
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(0, 0, 0)')

    await page.getByRole('radio', { name: 'Clair' }).check()
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(242, 242, 247)')
  })
})

test.describe('large screens', () => {
  test('use a side navigation instead of the bottom bar', async ({ page, isMobile }) => {
    test.skip(isMobile, 'desktop layout only')
    await page.goto('/')

    const box = await page.getByRole('navigation', { name: 'Navigation principale' }).boundingBox()
    const viewport = page.viewportSize()!
    expect(box).not.toBeNull()
    expect(box!.x).toBe(0)
    expect(box!.height).toBe(viewport.height)
    expect(box!.width).toBeLessThan(viewport.width / 4)
    await expect(page.getByRole('navigation', { name: 'Navigation principale' }).getByRole('link', { name: 'Réglages' })).toBeInViewport()
  })
})

test.describe('production serving', () => {
  test('security headers and a request id are set on the app and the API', async ({ request }) => {
    for (const path of ['/', '/api/health']) {
      const response = await request.get(path)
      const headers = response.headers()

      expect(headers['x-content-type-options'], path).toBe('nosniff')
      expect(headers['x-frame-options'], path).toBe('DENY')
      expect(headers['cross-origin-opener-policy'], path).toBe('same-origin')
      expect(headers['referrer-policy'], path).toBe('strict-origin-when-cross-origin')
      expect(headers.server, path).toBeUndefined()
      expect(headers['x-request-id'], path).toMatch(/^[0-9a-f-]{36}$/)
    }
  })

  test('hashed assets are immutable, the shell always revalidates, missing chunks are plain 404s', async ({ page, request }) => {
    await page.goto('/')
    const script = await page.locator('script[type="module"][src^="/_nuxt/"]').first().getAttribute('src')
    expect(script).toBeTruthy()

    expect((await request.get(script!)).headers()['cache-control']).toBe('public, max-age=31536000, immutable')
    expect((await request.get('/')).headers()['cache-control']).toBe('no-cache')
    expect((await request.get('/sw.js')).headers()['cache-control']).toBe('no-cache')

    const missing = await request.get('/_nuxt/does-not-exist.js')
    expect(missing.status()).toBe(404)
    expect(missing.headers()['cache-control']).toBeUndefined()
  })

  test('unknown API routes answer JSON, never the SPA', async ({ request }) => {
    const response = await request.get('/api/does-not-exist', { headers: { Accept: 'application/json' } })

    expect(response.status()).toBe(404)
    expect(response.headers()['content-type']).toContain('json')
  })

  test('the fake sign-in provider does not exist in production', async ({ request }) => {
    expect(await (await request.get('/api/auth/providers', { headers: { Accept: 'application/json' } })).json()).toEqual({ providers: [] })

    const start = await request.get('/api/auth/oauth/fake/start?login_hint=anyone@example.com', { maxRedirects: 0 })
    expect(start.status()).toBe(302)
    expect(start.headers().location).toBe('/connexion?oauth_error=identity.external_provider_unavailable')
  })

  test('health answers 200 with every dependency up', async ({ request }) => {
    const response = await request.get('/api/health', { headers: { Accept: 'application/json' } })

    expect(response.status()).toBe(200)
    expect(await response.json()).toEqual({ status: 'ok', checks: { database: 'up' }, monitors: null })
  })

  test('web app manifest is installable', async ({ request }) => {
    const response = await request.get('/manifest.webmanifest')
    expect(response.ok()).toBeTruthy()

    const manifest = await response.json() as { id: string, display: string, lang: string, icons: Array<{ purpose?: string }> }
    expect(manifest).toMatchObject({ id: '/', display: 'standalone', lang: 'fr' })
    expect(manifest.icons.some(icon => icon.purpose === 'maskable')).toBe(true)
  })

  test('the service worker installs so the shell works offline', async ({ page, context }) => {
    await page.goto('/')
    await page.evaluate(() => navigator.serviceWorker.ready)

    await context.setOffline(true)
    await page.reload()
    // The shell boots from the service worker cache; the server being unreachable is explained.
    await expect(page.getByRole('heading', { level: 1, name: 'Accueil' })).toBeVisible()
    await expect(page.getByText('Connexion impossible')).toBeVisible()
    await context.setOffline(false)
  })
})
