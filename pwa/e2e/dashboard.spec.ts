import { expectAccessible } from './support/accessibility'
import { expect, test } from './support/fixtures'

test.describe('dashboard', () => {
  test('a new account starts with its first steps; once one is done, the widgets, the views and the layout are the user\'s', async ({ page, account }) => {
    await page.goto('/')
    const main = page.getByRole('main')
    await expect(page.getByRole('heading', { level: 1, name: `Bonjour ${account.firstName}` })).toBeVisible()
    const start = main.getByRole('region', { name: 'Pour commencer' })
    await expect(start.getByRole('link', { name: 'Enregistrer ta première pesée' })).toBeVisible()
    await expect(main.getByRole('region', { name: 'Poids', exact: true })).toHaveCount(0)
    await expectAccessible(page)

    // A first weigh-in: that step goes, the widgets come.
    await start.getByRole('link', { name: 'Enregistrer ta première pesée' }).click()
    await expect(page.getByRole('textbox', { name: 'Poids' })).toBeFocused()
    await page.getByRole('textbox', { name: 'Poids' }).fill('51,2')
    await page.getByRole('button', { name: 'Enregistrer la pesée' }).click()
    await expect(page.getByText('Pesée du jour enregistrée')).toBeVisible()
    await page.goto('/')
    await expect(main.getByRole('region', { name: 'Poids', exact: true })).toContainText('51,2')
    await expect(main.getByRole('region', { name: 'Pour commencer' }).getByRole('link', { name: 'Enregistrer ta première pesée' })).toHaveCount(0)
    await expect(main.getByRole('region', { name: 'Coach' })).toContainText('Fixe un objectif calorique')
    await expectAccessible(page)

    // The radio is visually hidden in its pill: the user clicks the pill.
    await main.locator('label', { hasText: 'Semaine' }).click()
    await expect(main.getByRole('radio', { name: /^Semaine/ })).toBeChecked()
    await expect(main.getByRole('region', { name: 'Repas' })).toHaveCount(0)
    await expect(main.getByRole('region', { name: 'Rapport de la semaine' })).toContainText('première semaine complète')
    await main.locator('label', { hasText: 'Jour (aujourd’hui)' }).click()

    // Hide the weight, move the Coach to the top: kept for the account.
    await main.getByRole('button', { name: 'Personnaliser le tableau de bord' }).click()
    await expect(main.getByRole('heading', { name: 'Personnaliser le tableau de bord' })).toBeFocused()
    await main.getByRole('checkbox', { name: 'Poids', exact: true }).uncheck()
    for (let i = 0; i < 4; i++) await main.getByRole('button', { name: 'Monter le widget Coach' }).click()
    await expectAccessible(page)
    await main.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(page.getByRole('status').getByText('Tableau de bord enregistré.')).toBeVisible()
    await page.reload()
    await expect(main.getByRole('region', { name: 'Poids', exact: true })).toHaveCount(0)
    await expect(main.getByRole('region').filter({ has: page.getByRole('heading', { level: 2, name: 'Coach' }) })).toBeVisible()
  })

  test('the « + » leads to the weigh-in, and Ctrl+K finds a page or says when nothing is found', async ({ page, account: _account }) => {
    await page.goto('/')
    await page.getByRole('button', { name: 'Ajouter' }).click()
    const panel = page.getByRole('dialog', { name: 'Ajouter' })
    await expectAccessible(page)
    await panel.getByRole('link', { name: 'Pesée' }).click()
    await expect(page).toHaveURL(/\/progression$/)
    await expect(page.getByRole('dialog', { name: 'Ajouter une pesée' }).getByRole('textbox', { name: 'Poids' })).toBeFocused()
    await page.keyboard.press('Escape')

    await page.keyboard.press('Control+k')
    const search = page.getByRole('combobox', { name: 'Rechercher' })
    await expect(search).toBeFocused()
    await search.fill('zzzz')
    await expect(page.getByText('Rien trouvé pour « zzzz ».')).toBeVisible()
    await expectAccessible(page)
    await search.fill('mensur')
    await expect(page.getByRole('option', { name: 'Mensurations' })).toBeVisible()
    await expectAccessible(page)
    await page.keyboard.press('Enter')
    await expect(page).toHaveURL(/\/progression\/mensurations$/)
    await expect(page.getByRole('heading', { level: 1, name: 'Mensurations' })).toBeVisible()
  })
})
