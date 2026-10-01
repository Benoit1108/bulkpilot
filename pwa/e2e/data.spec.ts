import { expectAccessible } from './support/accessibility'
import { expect, PASSWORD, test } from './support/fixtures'

test.describe('personal data', () => {
  test('an export is asked for, made by the worker, downloaded, and the account activity says so', async ({ page, account: _account }) => {
    await page.goto('/reglages')
    await page.getByRole('link', { name: /Mes données/ }).click()
    await expect(page).toHaveURL(/\/reglages\/donnees$/)
    await expect(page.getByText('Aucun export pour l’instant.')).toBeVisible()
    await expectAccessible(page)

    // Nothing chosen: said on the first box, which gets the focus.
    for (const box of await page.getByRole('group', { name: 'Données' }).getByRole('checkbox').all()) await box.uncheck()
    await page.getByRole('button', { name: 'Préparer mon export' }).click()
    await expect(page.getByText('Choisis au moins une donnée à exporter.')).toBeVisible()
    await expect(page.getByRole('group', { name: 'Données' }).getByRole('checkbox').first()).toBeFocused()
    await expectAccessible(page)
    for (const box of await page.getByRole('group', { name: 'Données' }).getByRole('checkbox').all()) await box.check()

    await page.getByRole('button', { name: 'Préparer mon export' }).click()
    await expect(page.getByRole('status').getByText('Export demandé')).toBeVisible()
    // The worker of the E2E stack makes it; the page looks again until it is ready.
    await expect(page.getByRole('status').getByText('Ton export est prêt')).toBeVisible({ timeout: 30_000 })
    await expectAccessible(page)

    const url = await page.evaluate(async () => {
      const response = await fetch(`/api/exports/${(await (await fetch('/api/exports', { headers: { Accept: 'application/json' } })).json())[0].id}/download`, { method: 'POST', headers: { Accept: 'application/json' } })
      return (await response.json()).url as string
    })
    const archive = await page.request.get(url)
    expect(archive.ok()).toBe(true)
    expect(archive.headers()['content-type']).toContain('application/zip')
    expect((await archive.body()).subarray(0, 2).toString()).toBe('PK')

    // The page stays usable after a download.
    await page.getByRole('button', { name: /^Télécharger/ }).click()
    await expect(page.getByRole('heading', { level: 1, name: 'Mes données' })).toBeVisible()
    await page.getByRole('link', { name: 'Réglages' }).first().click()
    await expect(page).toHaveURL(/\/reglages$/)

    await page.goto('/reglages/activite')
    await expect(page.getByRole('list', { name: /Activité/ }).getByText('Export demandé')).toBeVisible()
    await expect(page.getByRole('list', { name: /Activité/ }).getByText('Création du compte')).toBeVisible()
    await expectAccessible(page)
  })

  test('deleting the account signs out everywhere, and signing in again within the delay keeps it', async ({ page, account }) => {
    await page.goto('/reglages/compte')
    await page.getByRole('link', { name: 'Supprimer mon compte' }).click()
    await expect(page).toHaveURL(/\/reglages\/suppression$/)
    await expect(page.getByText(/puis effacé le/)).toBeVisible()
    await expectAccessible(page)

    // Nothing filled in: the first field to fix gets the focus.
    await page.getByRole('button', { name: 'Supprimer mon compte' }).click()
    await expect(page.getByLabel('Ton mot de passe')).toBeFocused()
    await expectAccessible(page)

    await page.getByLabel('Ton mot de passe').fill(PASSWORD)
    await page.getByLabel('Écris SUPPRIMER pour confirmer').fill('SUPPRIMER')
    await page.getByRole('button', { name: 'Supprimer mon compte' }).click()
    await expect(page).toHaveURL(/\/connexion\?suppression=/)
    await expect(page.getByText(/Ton compte sera supprimé le/)).toBeVisible()
    await expectAccessible(page)
    // Signed out for real: the API refuses the old session.
    expect((await page.request.get('/api/me', { headers: { Accept: 'application/json' } })).status()).toBe(401)

    await page.getByLabel('Email').fill(account.email)
    await page.getByRole('textbox', { name: 'Mot de passe' }).fill(PASSWORD)
    await page.getByRole('button', { name: 'Se connecter' }).click()
    // The banner (a visually hidden live region says it too, more briefly).
    await expect(page.getByText(/La suppression de ton compte est annulée\s*: tout est conservé/)).toBeVisible()
    await expectAccessible(page)
  })
})
