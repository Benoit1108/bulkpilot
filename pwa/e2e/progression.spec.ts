import { expectAccessible } from './support/accessibility'
import type { Page } from '@playwright/test'
import { expect, test } from './support/fixtures'

function day(daysAgo: number): string {
  const date = new Date()
  date.setDate(date.getDate() - daysAgo)
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

/** Weigh-ins of the past weeks, straight through the API (the form has its own step below). */
async function weighInsOfTheLastWeeks(page: Page): Promise<void> {
  for (const [ago, kg] of [[21, 48.0], [14, 48.6], [7, 49.1]] as const) {
    const response = await page.request.put(`/api/progression/weigh-ins/${day(ago)}`, { data: { weightKg: kg }, headers: { Accept: 'application/json' } })
    expect(response.status(), await response.text()).toBe(200)
  }
}

test.describe('progression', () => {
  test('a weigh-in, the averages and the trend, then a goal with milestones and its estimate', async ({ page, account: _account }) => {
    await weighInsOfTheLastWeeks(page)
    await page.goto('/progression')
    await expect(page.getByRole('textbox', { name: 'Poids' })).toHaveValue('49,1')
    // Spec §112: the averages are worked out again from the new weigh-in.
    const figure = (label: string) => page.locator('dt', { hasText: label }).locator('xpath=following-sibling::dd')
    await expect(figure('Moyenne 7 jours')).toHaveText('aucune pesée ces 7 derniers jours')
    await expect(figure('Moyenne 30 jours')).toHaveText('48,6 kg')

    await page.getByRole('textbox', { name: 'Poids' }).fill('49,6')
    await page.getByRole('button', { name: 'Enregistrer la pesée' }).click()
    await expect(page.getByText('Pesée du jour enregistrée : 49,6 kg.')).toBeVisible()
    await expect(figure('Moyenne 7 jours')).toHaveText('49,6 kg')
    await expect(figure('Moyenne 30 jours')).toHaveText('48,8 kg')
    await expect(page.getByText('en hausse (+0,53 kg/semaine)')).toBeVisible()
    await expect(page.getByText('Aucun objectif de poids')).toBeVisible()
    await expectAccessible(page)

    const chart = page.getByRole('group', { name: 'Poids' })
    await chart.focus()
    await page.keyboard.press('End')
    await expect(page.getByRole('figure').getByRole('status')).toContainText('Pesées : 49,6 kg')
    await page.getByText('Voir les valeurs').click()
    await expect(page.getByRole('table')).toContainText('49,6 kg')

    await page.getByRole('link', { name: 'Fixer un objectif' }).click()
    await page.getByLabel('Poids visé').fill('55')
    await page.getByRole('button', { name: 'Ajouter un jalon' }).click()
    await page.getByLabel('Jalon 1', { exact: true }).fill('52')
    await page.getByRole('button', { name: 'Ajouter un jalon' }).click()
    await page.getByLabel('Jalon 2', { exact: true }).fill('50')
    await expectAccessible(page)
    await page.getByRole('button', { name: 'Suivre cet objectif' }).click()

    await expect(page).toHaveURL(/\/progression$/)
    const goal = page.getByRole('region', { name: 'Objectif' })
    await expect(goal).toContainText('De 49,6 kg')
    await expect(goal.getByText('Jalon 50,0 kg')).toBeVisible()
    await expect(goal.getByText('0,4 kg à prendre — environ 1 semaine au rythme actuel (estimation statistique)')).toBeVisible()
    await expectAccessible(page)

    await page.getByRole('link', { name: 'Toutes les pesées' }).click()
    await page.getByRole('button', { name: /^Retirer la pesée du/ }).last().click()
    await page.getByRole('button', { name: 'Confirmer' }).click()
    await expect(page.getByText(/^Pesée du .* retirée\.$/)).toBeVisible()
    await expect(page.getByRole('main').getByRole('listitem')).toHaveCount(3)
  })

  test('measurements, a measure of one\'s own, and their evolution', async ({ page, account: _account }) => {
    await page.goto('/progression/mesures')
    await page.getByLabel('Nouvelle mesure').fill('Hanches')
    await page.getByRole('button', { name: 'Ajouter la mesure' }).click()
    await expect(page.getByText('Mesure « Hanches » ajoutée.')).toBeVisible()

    await page.getByRole('link', { name: 'Mensurations' }).click()
    await page.getByLabel('Taille').fill('78,5')
    await page.getByLabel('Hanches').fill('94')
    await page.getByRole('button', { name: 'Enregistrer les mensurations' }).click()
    await expect(page.getByText('Mensurations enregistrées (2).')).toBeVisible()
    await expect(page.getByRole('row', { name: /Taille 78,5 cm/ })).toBeVisible()
    await expectAccessible(page)
  })

  test('photos kept in the bucket, compared side by side, deleted for good', async ({ page, account: _account }) => {
    // A 64 × 96 PNG: the server re-encodes it to WebP and keeps it in the S3-compatible bucket.
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAEAAAABgCAIAAAAip+O/AAAAZ0lEQVR42u3PQQkAAAgEsEty/aMYy78NhMEKLNO+FgEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQGBawHrW2FpeFBBKwAAAABJRU5ErkJggg==', 'base64')
    await page.goto('/progression/photos')
    const upload = page.getByRole('region', { name: 'Ajouter une photo' })
    for (const when of [day(30), day(0)]) {
      await page.getByLabel('Photo', { exact: true }).setInputFiles({ name: 'IMG_2041.png', mimeType: 'image/png', buffer: png })
      // After a photo the next pose is offered: both are taken from the front here.
      await upload.getByText('Face', { exact: true }).click()
      await page.getByLabel('Jour').fill(when)
      await page.getByRole('button', { name: 'Ajouter la photo' }).click()
      await expect(page.getByText('Photo ajoutée (face).')).toBeVisible()
    }

    const compare = page.getByRole('region', { name: 'Comparer' })
    await expect(compare.getByRole('img')).toHaveCount(2)
    for (const img of await compare.getByRole('img').all()) {
      // Loaded through a presigned link, over TLS, from the bucket.
      await expect.poll(() => img.evaluate(element => (element as HTMLImageElement).naturalWidth)).toBeGreaterThan(0)
      expect(await img.getAttribute('src')).toMatch(/^https:\/\/s3-tls\/bulkpilot-photos\/[0-9a-f-]{36}\.webp\?.*X-Amz-Signature=/)
    }
    // Without its signature, the same address is refused: the bucket is private.
    const signed = (await compare.getByRole('img').first().getAttribute('src'))!
    expect((await page.request.get(signed.split('?')[0]!)).status()).toBe(403)
    await compare.getByText('Curseur').click()
    await expect(compare.getByLabel('Curseur (à gauche l’avant, à droite l’après)')).toBeVisible()
    await expectAccessible(page)

    await page.getByRole('button', { name: /^Face, / }).first().click()
    const photo = page.getByRole('dialog')
    await expect(photo.getByRole('link', { name: 'Télécharger' })).toHaveAttribute('href', /response-content-disposition=attachment/)
    const deleted = (await photo.getByRole('img').getAttribute('src'))!
    expect((await page.request.get(deleted)).status()).toBe(200)
    await expectAccessible(page)
    await photo.getByRole('button', { name: 'Supprimer la photo' }).click()
    await photo.getByRole('button', { name: 'Confirmer (elle sera effacée)' }).click()
    await expect(page.getByRole('region', { name: 'Comparer' })).toBeHidden()
    await expect(page.getByText('Photo supprimée.')).toBeVisible()
    // Deleted for good: even its still-signed link finds nothing in the bucket.
    await expect.poll(async () => (await page.request.get(deleted)).status()).toBe(404)
  })

  test('the analytics set calories against weight, and sum up the training', async ({ page, account: _account }) => {
    await weighInsOfTheLastWeeks(page)
    await page.goto('/progression/analyses')

    await expect(page.getByRole('region', { name: 'Calories face au poids' })).toContainText('Il faut au moins deux semaines')
    await expect(page.getByRole('region', { name: 'Entraînement' })).toContainText('Séances0')
    await page.getByText('Tout').click()
    await expect(page.getByRole('group', { name: 'Poids moyen par semaine' })).toBeVisible()
    await expectAccessible(page)
  })
})
