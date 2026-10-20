import type { Browser, BrowserContext, Page } from '@playwright/test'
import { accessibilityProblems, useLightTheme } from './support/accessibility'
import { expect, JSON_HEADERS, registerThroughApi, test, uniqueEmail } from './support/fixtures'
import { layoutProblems, settled, spaceText } from './support/layout'
import { populate, type Populated } from './support/seed'

/**
 * Every page, at the widths the app is used at (spec §2.1 mobile first) and zoomed in, on an
 * account that has used everything: nothing scrolls sideways, cuts text off, overlaps, hides under
 * a fixed element, is too small to press, or breaks the title inside a word. At the phone width,
 * each page is also checked by axe in both themes, and with the user's own text spacing.
 */
interface Size { width: number, height: number, touch: boolean }
const PHONE: Size = { width: 390, height: 844, touch: true }
const WIDTHS: Size[] = [
  { width: 320, height: 640, touch: true },
  PHONE,
  { width: 768, height: 1024, touch: true },
  { width: 1280, height: 800, touch: false },
  // Zoomed in on a desktop (WCAG 1.4.10 reflow): 1280 × 1024 at 400 %, 1280 × 800 at 200 %.
  { width: 320, height: 256, touch: false },
  { width: 640, height: 400, touch: false },
]

const SECTIONS: Record<string, (ids: Populated) => string[]> = {
  'home, Coach, notifications': () => ['/', '/coach', '/coach/historique', '/coach/rapports', '/coach/reglages', '/notifications', '/notifications/reglages'],
  'nutrition': ids => ['/nutrition', '/nutrition/objectifs', '/nutrition/repartition', '/nutrition/favoris', '/nutrition/aliments', '/nutrition/aliments/nouveau', `/nutrition/aliments/${ids.foodId}`, '/nutrition/recettes', '/nutrition/recettes/nouvelle', `/nutrition/recettes/${ids.recipeId}`],
  'training': ids => ['/training', '/training/programmes', '/training/programmes/nouveau', `/training/programmes/${ids.programId}`, '/training/exercices', '/training/exercices/nouveau', `/training/exercices/${ids.exerciseId}`, '/training/seances', `/training/seances/${ids.workoutId}`],
  'progression': () => ['/progression', '/progression/objectif', '/progression/pesees', '/progression/mensurations', '/progression/mesures', '/progression/photos', '/progression/analyses'],
  'settings': () => ['/reglages', '/reglages/profil', '/reglages/compte', '/reglages/donnees', '/reglages/activite', '/reglages/suppression', '/reglages/credits'],
}
const SIGNED_OUT = ['/connexion', '/inscription', '/mot-de-passe-oublie', '/reinitialiser-mot-de-passe?token=x', '/verifier-email?token=x', '/nope']

/** Contexts made here do not inherit the config's `use`: dark, as the device setting the first pass checks. */
const BASE = { baseURL: process.env.E2E_BASE_URL ?? 'https://localhost', ignoreHTTPSErrors: true, colorScheme: 'dark' as const }

function device(browser: Browser, size: Size, storageState?: Awaited<ReturnType<BrowserContext['storageState']>>): Promise<BrowserContext> {
  return browser.newContext({ ...BASE, storageState, viewport: { width: size.width, height: size.height }, hasTouch: size.touch, isMobile: size.touch && size.width < 768 })
}

/**
 * Runs `check` in its own context, closed whatever happens. Playwright traces contexts made from
 * the test's browser too (the config's `trace`): a failure keeps one trace per size.
 */
async function within(context: BrowserContext, check: (page: Page) => Promise<string[]>): Promise<string[]> {
  try {
    return await check(await context.newPage())
  }
  finally {
    await context.close()
  }
}

/** Escape until nothing is open (the search clears its query on the first one). */
async function closeDialog(page: Page): Promise<void> {
  for (let tries = 0; tries < 3 && await page.locator('dialog[open]').count(); tries++) await page.keyboard.press('Escape')
  await expect(page.locator('dialog[open]')).toHaveCount(0)
}

async function visit(page: Page, path: string): Promise<void> {
  await page.goto(path)
  await settled(page)
  await expect(page.getByRole('heading', { level: 1 }), path).toBeVisible()
}

/** Layout at this size; at the phone width, axe in both themes and the text spacing too. */
async function pageProblems(page: Page, path: string, size: Size): Promise<string[]> {
  await visit(page, path)
  const found = (await layoutProblems(page, size.touch)).map(problem => `${size.width}×${size.height} ${path}: ${problem}`)
  if (size !== PHONE) return found
  found.push(...(await accessibilityProblems(page)).map(problem => `axe ${path}: ${problem}`))
  await spaceText(page)
  found.push(...(await layoutProblems(page, size.touch)).map(problem => `text spacing ${path}: ${problem}`))
  return found
}

/** The same, in the light theme (axe only: the layout does not change with the colours). */
async function lightProblems(page: Page, paths: string[]): Promise<string[]> {
  await page.goto('/')
  await useLightTheme(page)
  const found: string[] = []
  for (const path of paths) {
    await visit(page, path)
    found.push(...(await accessibilityProblems(page)).map(problem => `axe light ${path}: ${problem}`))
  }
  return found
}

// Sizes are set per context: once is enough, on one browser project.
test.describe('every page is laid out at every width', () => {
  test.describe.configure({ mode: 'parallel' })
  test.skip(({ isMobile }) => isMobile, 'sizes are set per context: run once, on the desktop project')

  for (const [section, pathsOf] of Object.entries(SECTIONS)) {
    test(section, async ({ browser }) => {
      test.setTimeout(300_000)
      // One account that used everything, signed in once; every size reuses its session.
      const setup = await browser.newContext(BASE)
      const seeding = await setup.newPage()
      await registerThroughApi(seeding, { email: uniqueEmail('layout'), firstName: 'Alexandrine-Marie' })
      const ids = await populate(seeding)
      if (section === 'training') {
        // A workout in progress: the page used at the gym is checked too.
        await seeding.request.put(`/api/workouts/${crypto.randomUUID()}`, { data: { startedAt: new Date().toISOString() }, headers: JSON_HEADERS })
      }
      const session = await setup.storageState()
      await setup.close()
      const paths = pathsOf(ids)

      const problems: string[] = []
      for (const size of WIDTHS) {
        problems.push(...await within(await device(browser, size, session), async (page) => {
          const found: string[] = []
          for (const path of paths) found.push(...await pageProblems(page, path, size))
          if (section === 'training') {
            await visit(page, '/training')
            await page.getByRole('button', { name: 'Reprendre' }).click()
            await expect(page).toHaveURL(/\/training\/seance$/)
            await settled(page)
            found.push(...(await layoutProblems(page, size.touch)).map(problem => `${size.width}×${size.height} /training/seance: ${problem}`))
            if (size === PHONE) found.push(...(await accessibilityProblems(page)).map(problem => `axe /training/seance: ${problem}`))
          }
          return found
        }))
      }
      problems.push(...await within(await device(browser, PHONE, session), page => lightProblems(page, paths)))
      expect(problems).toEqual([])
    })
  }

  test('signed-out pages', async ({ browser }) => {
    test.setTimeout(180_000)
    const problems: string[] = []
    for (const size of WIDTHS) {
      problems.push(...await within(await device(browser, size), async (page) => {
        const found: string[] = []
        for (const path of SIGNED_OUT) found.push(...await pageProblems(page, path, size))
        return found
      }))
    }
    problems.push(...await within(await device(browser, PHONE), page => lightProblems(page, SIGNED_OUT)))
    expect(problems).toEqual([])
  })

  test('what opens over a page: the « + » panel, the search, a drawer, the dashboard settings', async ({ browser }) => {
    test.setTimeout(180_000)
    const setup = await browser.newContext(BASE)
    const seeding = await setup.newPage()
    await registerThroughApi(seeding, { email: uniqueEmail('overlays'), firstName: 'Alex' })
    await populate(seeding)
    const session = await setup.storageState()
    await setup.close()

    const problems: string[] = []
    for (const size of [PHONE, { width: 1280, height: 800, touch: false }]) {
      problems.push(...await within(await device(browser, size, session), async (page) => {
        const found: string[] = []
        const check = async (what: string) => test.step(`${size.width} ${what}`, async () => {
          found.push(...(await layoutProblems(page, size.touch)).map(problem => `${size.width} ${what}: ${problem}`))
          found.push(...(await accessibilityProblems(page)).map(problem => `axe ${size.width} ${what}: ${problem}`))
        })
        await visit(page, '/')
        await page.getByRole('button', { name: 'Ajouter', exact: true }).click()
        await expect(page.getByRole('dialog', { name: 'Ajouter' })).toBeVisible()
        await check('« + » panel')
        await closeDialog(page)

        await page.keyboard.press('Control+k')
        await expect(page.getByRole('combobox', { name: 'Rechercher' })).toBeFocused()
        await page.keyboard.type('riz')
        await check('search palette')
        await closeDialog(page)

        await page.getByRole('button', { name: 'Personnaliser le tableau de bord' }).click()
        await expect(page.getByRole('heading', { name: 'Personnaliser le tableau de bord' })).toBeVisible()
        await check('dashboard settings')

        await visit(page, '/nutrition')
        await page.getByRole('button', { name: 'Ajouter un aliment au petit-déjeuner' }).click()
        await expect(page.getByRole('dialog')).toBeVisible()
        await check('add-food drawer')
        return found
      }))
    }
    expect(problems).toEqual([])
  })
})
