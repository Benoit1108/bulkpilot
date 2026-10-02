import { mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { chromium, expect, test, type BrowserContext } from '@playwright/test'
import lighthouse from 'lighthouse'
import { registerThroughApi, uniqueEmail } from '../e2e/support/fixtures'
import { populate } from '../e2e/support/seed'

/**
 * Lighthouse (mobile, simulated throttling) on the production image: the app is an installable
 * PWA, and its key pages stay fast, accessible and sound. Lighthouse 12 dropped its PWA category:
 * installability is asked from Chrome itself (`Page.getInstallabilityErrors`).
 *
 * Thresholds: accessibility and best practices 0.95. Performance 0.85 on a phone with a slow 4G
 * link, as Lighthouse simulates it. A shared CI machine makes one run noisy (blocking time
 * follows the machine's load): each page is measured three times and the median run counts, as
 * Lighthouse CI does. Every run is a first visit: the service worker and its caches, and the HTTP
 * cache, are cleared before it (the session cookie and the device's own data stay).
 *
 * Accessibility here is a score on a subset of axe's rules: the reference is axe itself, run by
 * the E2E specs (`expectAccessible`) on every page; this budget only guards the key pages too.
 */
const THRESHOLDS = { 'performance': 0.85, 'accessibility': 0.95, 'best-practices': 0.95 } as const
const RUNS = 3
const REPORTS = 'lighthouse-report'

type Category = keyof typeof THRESHOLDS
type Scores = Record<Category, number>

test('the app is installable and its key pages meet the budgets', async ({ baseURL }) => {
  const profile = await mkdtemp(join(tmpdir(), 'lighthouse-'))
  // Any free port (a shared machine may have one taken): Chrome writes the one it chose.
  const context = await chromium.launchPersistentContext(profile, {
    baseURL,
    ignoreHTTPSErrors: true,
    args: ['--remote-debugging-port=0', '--ignore-certificate-errors'],
  })
  try {
    await measure(context, Number((await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]), baseURL!)
  }
  finally {
    await context.close()
    await rm(profile, { recursive: true, force: true })
  }
})

async function measure(context: BrowserContext, port: number, baseURL: string): Promise<void> {
  const page = context.pages()[0] ?? await context.newPage()
  await mkdir(REPORTS, { recursive: true })
  const scores: Record<string, Scores> = {}
  const devtools = await context.newCDPSession(page)
  const origin = new URL(baseURL).origin

  async function audit(path: string): Promise<void> {
    const runs = []
    for (let run = 0; run < RUNS; run++) {
      // A first visit each time: no service worker, no cached responses (the cookies stay).
      await devtools.send('Storage.clearDataForOrigin', { origin, storageTypes: 'cache_storage,service_workers' })
      await devtools.send('Network.clearBrowserCache')
      const result = await lighthouse(`${baseURL}${path}`, {
        port,
        output: 'html',
        logLevel: 'error',
        onlyCategories: Object.keys(THRESHOLDS),
        // The signed-in session lives in the browser's cookies: keep them between pages.
        disableStorageReset: true,
      })
      if (!result) throw new Error(`Lighthouse returned nothing for ${path}`)
      runs.push(result)
    }
    runs.sort((a, b) => (a.lhr.categories.performance?.score ?? 0) - (b.lhr.categories.performance?.score ?? 0))
    const median = runs[Math.floor(RUNS / 2)]!
    const name = path === '/' ? 'accueil' : path.replace(/^\//, '').replaceAll('/', '-')
    await writeFile(join(REPORTS, `${name}.html`), median.report as string)
    scores[path] = Object.fromEntries(Object.keys(THRESHOLDS).map(key => [key, median.lhr.categories[key]?.score ?? 0])) as Scores
  }

  await page.goto('/connexion')
  await page.evaluate(() => navigator.serviceWorker.ready)
  const { installabilityErrors } = await devtools.send('Page.getInstallabilityErrors')
  expect(installabilityErrors, 'Chrome would offer to install the app').toEqual([])
  await audit('/connexion')

  // Signed in, on an account that used everything, and in a workout (the page used at the gym).
  await registerThroughApi(page, { email: uniqueEmail('lighthouse'), firstName: 'Alex' })
  await populate(page)
  await page.goto('/training')
  await page.getByRole('button', { name: 'Démarrer Full A' }).click()
  await expect(page).toHaveURL(/\/training\/seance$/)
  // The pages used most, and the heaviest one (three charts over the period).
  for (const path of ['/', '/nutrition', '/training', '/training/seance', '/progression', '/progression/analyses', '/coach']) await audit(path)

  await writeFile(join(REPORTS, 'scores.json'), `${JSON.stringify(scores, null, 2)}\n`)
  // The scores in the CI log, whatever the outcome.
  process.stdout.write(`${Object.entries(scores).map(([path, score]) => `${path}: ${Object.entries(score).map(([key, value]) => `${key} ${Math.round(value * 100)}`).join(', ')}`).join('\n')}\n`)

  const below = Object.entries(scores).flatMap(([path, score]) => (Object.keys(THRESHOLDS) as Category[])
    .filter(key => score[key] < THRESHOLDS[key])
    .map(key => `${path}: ${key} ${Math.round(score[key] * 100)} < ${THRESHOLDS[key] * 100}`))
  expect(below, `reports in ${REPORTS}/`).toEqual([])
}
