import { expectAccessible } from './support/accessibility'
import { expect, test } from './support/fixtures'

test.describe('coach', () => {
  test('a first analysis, the answers, the tone of the Coach and the history', async ({ page, account: _account }) => {
    await page.goto('/coach')
    await expect(page.getByText('Rien ne change sans ton accord.')).toBeVisible()
    const first = page.getByRole('article', { name: 'Fixe un objectif calorique' })
    await expect(first).toContainText('Information')
    await expect(first.getByRole('link', { name: 'Fixer mon objectif' })).toHaveAttribute('href', '/nutrition/objectifs')
    await expect(page.getByRole('article', { name: 'Pèse-toi plus souvent' })).toBeVisible()
    await expect(page.getByText('Ton adhérence s’affichera après quelques jours de saisie.')).toBeVisible()
    await first.getByText('Pourquoi ?').click()
    await expect(first.getByRole('listitem')).toContainText('Sans objectif')
    await expectAccessible(page)

    await first.getByRole('button', { name: 'C’est noté' }).click()
    await expect(page.getByRole('status').getByText('C’est noté.')).toBeVisible()
    await expect(page.getByRole('article', { name: 'Fixe un objectif calorique' })).toHaveCount(0)
    // Focus moves to the one that took its place.
    await expect(page.getByRole('heading', { name: /Pèse-toi plus souvent/ })).toBeFocused()

    await page.getByRole('article', { name: 'Pèse-toi plus souvent' }).getByRole('button', { name: 'Plus tard' }).click()
    await expect(page.getByText('Rien à signaler : continue ton plan.')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Recommandations' })).toBeFocused()
    await expectAccessible(page)

    await page.getByRole('navigation', { name: 'Le Coach' }).getByRole('link', { name: /^Préférences du Coach/ }).click()
    await expect(page).toHaveURL(/\/coach\/reglages$/)
    await page.getByLabel('Encourageante').check()
    await page.getByLabel('Rythme visé').fill('un demi')
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click()
    await expect(page.getByText('Un nombre de kilos par semaine, par exemple +0,5 ou −0,3.')).toBeVisible()
    await expectAccessible(page)
    await page.getByLabel('Rythme visé').fill('+0,5')
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click()
    await expect(page.getByText('Préférences du Coach enregistrées.')).toBeVisible()
    await page.reload()
    await expect(page.getByLabel('Encourageante')).toBeChecked()
    await expect(page.getByLabel('Rythme visé')).toHaveValue('+0,5')

    await page.goto('/coach/historique')
    await expect(page.getByRole('listitem').filter({ hasText: 'Fixe un objectif calorique' })).toContainText('Vue')
    await expect(page.getByRole('listitem').filter({ hasText: 'Pèse-toi plus souvent' })).toContainText('Reportée')
    await expectAccessible(page)

    await page.goto('/coach/rapports')
    await expect(page.getByText('Aucun rapport pour l’instant')).toBeVisible()
    await expectAccessible(page)
  })
})
