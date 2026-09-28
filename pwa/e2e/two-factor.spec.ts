import { linkFromLastEmail } from './support/mailbox'
import { totp } from './support/totp'
import { expect, PASSWORD, test } from './support/fixtures'

test('two-factor authentication: enrol, sign in with the app, then with a recovery code', async ({ page, account }) => {
  await page.goto(await linkFromLastEmail(page.request, account.email, '/verifier-email'))
  await expect(page.getByText('Ton adresse email est confirmée.')).toBeVisible()

  await page.goto('/reglages/compte')
  const card = page.getByRole('region', { name: 'Double authentification' })
  await card.getByRole('button', { name: 'Activer' }).click()
  await card.getByLabel('Confirme ton mot de passe', { exact: true }).fill(PASSWORD)
  await card.getByRole('button', { name: 'Continuer' }).click()

  await expect(card.getByRole('img', { name: /QR code/ })).toBeVisible()
  const secret = (await card.getByTestId('totp-secret').textContent())!.replace(/\s/g, '')
  await card.getByRole('textbox', { name: 'Code à 6 chiffres affiché par l’application' }).fill(totp(secret))
  await card.getByRole('button', { name: 'Activer' }).click()

  await expect(card.getByTestId('recovery-codes').locator('li')).toHaveCount(10)
  const codes = await card.getByTestId('recovery-codes').locator('li').allTextContents()
  await card.getByRole('button', { name: 'Télécharger' }).click()
  await card.getByRole('button', { name: 'J’ai enregistré mes codes' }).click()
  await expect(card.getByText('Active : un code de ton application est demandé à chaque connexion.')).toBeVisible()

  await page.goto('/reglages')
  await page.getByRole('button', { name: 'Se déconnecter' }).click()
  await expect(page).toHaveURL(/\/connexion$/)
  await page.getByRole('textbox', { name: 'Email' }).fill(account.email)
  await page.getByLabel('Mot de passe', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Se connecter' }).click()

  // The enrolment code's time step is spent: the app's next code is accepted (±1 step of drift).
  await page.getByRole('textbox', { name: 'Code de vérification' }).fill(totp(secret, Date.now() + 30_000))
  await page.getByRole('button', { name: 'Valider' }).click()
  await expect(page.getByText('Bonjour Alex')).toBeVisible()

  await page.goto('/reglages')
  await page.getByRole('button', { name: 'Se déconnecter' }).click()
  await expect(page).toHaveURL(/\/connexion$/)
  await page.getByRole('textbox', { name: 'Email' }).fill(account.email)
  await page.getByLabel('Mot de passe', { exact: true }).fill(PASSWORD)
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await page.getByRole('button', { name: 'Utiliser un code de secours' }).click()
  await page.getByRole('textbox', { name: 'Code de secours' }).fill(codes[0]!.trim())
  await page.getByRole('button', { name: 'Valider' }).click()
  await expect(page.getByText('Bonjour Alex')).toBeVisible()
})
