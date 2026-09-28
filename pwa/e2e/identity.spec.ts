import { linkFromLastEmail } from './support/mailbox'
import { expect, newDevice, PASSWORD, registerThroughApi, test, uniqueEmail } from './support/fixtures'

test.describe('account', () => {
  test('sign up, confirm the email from the inbox, and be greeted', async ({ page }) => {
    const email = uniqueEmail('signup')
    await page.goto('/inscription')

    await page.getByLabel('Prénom').fill('Camille')
    await page.getByRole('textbox', { name: 'Email' }).fill(email)
    await page.getByLabel('Mot de passe', { exact: true }).fill(PASSWORD)
    await page.getByRole('button', { name: 'Créer mon compte' }).click()

    await expect(page.getByText('Bonjour Camille')).toBeVisible()
    await expect(page.getByText('Confirme ton adresse email')).toBeVisible()

    await page.goto(await linkFromLastEmail(page.request, email, '/verifier-email'))
    await expect(page.getByText('Ton adresse email est confirmée.')).toBeVisible()
    await page.getByRole('link', { name: 'Aller à l’accueil' }).click()
    await expect(page.getByText('Bonjour Camille')).toBeVisible()
    await expect(page.getByText('Confirme ton adresse email')).toBeHidden()
  })

  test('private pages send visitors to sign-in, then back where they were going', async ({ page, account }) => {
    await page.goto('/reglages')
    await page.getByRole('button', { name: 'Se déconnecter' }).click()
    await expect(page).toHaveURL(/\/connexion$/)

    await page.goto('/training')
    await expect(page).toHaveURL(/\/connexion\?redirect=\/training$/)

    await page.getByRole('textbox', { name: 'Email' }).fill(account.email)
    await page.getByLabel('Mot de passe', { exact: true }).fill('not the password')
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page.getByRole('alert')).toHaveText('Email ou mot de passe incorrect.')

    await page.getByLabel('Mot de passe', { exact: true }).fill(PASSWORD)
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page.getByRole('heading', { level: 1, name: 'Training' })).toBeVisible()
  })

  test('a forgotten password is reset from the emailed link', async ({ page, account }) => {
    await page.goto(await linkFromLastEmail(page.request, account.email, '/verifier-email'))
    await expect(page.getByText('Ton adresse email est confirmée.')).toBeVisible()
    await page.request.post('/api/auth/sign-out')

    await page.goto('/mot-de-passe-oublie')
    await page.getByRole('textbox', { name: 'Email' }).fill(account.email)
    await page.getByRole('button', { name: 'Envoyer le lien' }).click()
    await expect(page.getByText('un lien de réinitialisation vient d’y être envoyé')).toBeVisible()

    await page.goto(await linkFromLastEmail(page.request, account.email, '/reinitialiser-mot-de-passe'))
    await page.getByRole('textbox', { name: 'Nouveau mot de passe' }).fill('a brand new passphrase')
    await page.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(page.getByText('tous tes appareils ont été déconnectés')).toBeVisible()

    await page.goto('/connexion')
    await page.getByRole('textbox', { name: 'Email' }).fill(account.email)
    await page.getByLabel('Mot de passe', { exact: true }).fill('a brand new passphrase')
    await page.getByRole('button', { name: 'Se connecter' }).click()
    await expect(page.getByText('Bonjour Alex')).toBeVisible()
  })

  test('the profile is saved and shown again after a reload', async ({ page, account: _account }) => {
    await page.goto('/reglages')
    await page.getByRole('link', { name: /Profil/ }).click()

    await page.getByLabel('Prénom').fill('Alexandre')
    await page.getByLabel('Date de naissance').fill('2000-01-15')
    await page.getByLabel('Sexe').selectOption('male')
    await page.getByLabel('Taille').fill('178')
    await page.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(page.getByText('Profil enregistré.')).toBeVisible()

    await page.reload()
    await expect(page.getByLabel('Prénom')).toHaveValue('Alexandre')
    await expect(page.getByLabel('Taille')).toHaveValue('178')
    await expect(page.getByLabel('Date de naissance')).toHaveValue('2000-01-15')
  })
})

test.describe('devices', () => {
  test('another device can be signed out remotely', async ({ page, browser, account }) => {
    const phone = await newDevice(browser, 'Mozilla/5.0 (iPhone; CPU iPhone OS 19_0 like Mac OS X) Safari/604.1')
    const phonePage = await phone.newPage()
    const signIn = await phonePage.request.post('/api/auth/sign-in', { data: { email: account.email, password: PASSWORD } })
    expect(signIn.ok()).toBeTruthy()

    await page.goto('/reglages/compte')
    await expect(page.getByText('Cet appareil')).toBeVisible()
    await page.getByRole('button', { name: 'Déconnecter Safari · iOS' }).click()
    await expect(page.getByRole('button', { name: 'Déconnecter Safari · iOS' })).toBeHidden()

    await phonePage.goto('/reglages/compte')
    await expect(phonePage).toHaveURL(/\/connexion/)
    await phone.close()
  })
})

test.describe('worker mode', () => {
  // Three users (an odd number): with FrankenPHP's round-robin over an even number of worker threads,
  // each thread serves every user in turn. The deterministic kernel-level check is WorkerModeTest (PHPUnit).
  test('users alternating on the PHP workers never see each other', async ({ browser }) => {
    const contexts = await Promise.all([0, 1, 2].map(() => newDevice(browser)))
    const pages = await Promise.all(contexts.map(context => context.newPage()))
    const accounts = [{ email: uniqueEmail('leak-a'), firstName: 'Ana' }, { email: uniqueEmail('leak-b'), firstName: 'Bob' }, { email: uniqueEmail('leak-c'), firstName: 'Cam' }]
    for (const [i, page] of pages.entries()) await registerThroughApi(page, accounts[i]!)

    for (let round = 0; round < 40; round++) {
      for (const [i, page] of pages.entries()) {
        const me = await page.request.get('/api/me', { headers: { Accept: 'application/json' } })
        expect(me.status()).toBe(200)
        expect((await me.json() as { email: string }).email).toBe(accounts[i]!.email)
      }
    }

    // A failed sign-in in between does not poison the next request either.
    const anonymous = await newDevice(browser)
    const failed = await anonymous.request.post('/api/auth/sign-in', { data: { email: accounts[0]!.email, password: 'wrong password' } })
    expect(failed.status()).toBe(401)
    expect((await (await anonymous.request.get('/api/me')).json() as { code: string }).code).toBe('shared.not_authenticated')
    expect((await (await pages[1]!.request.get('/api/me', { headers: { Accept: 'application/json' } })).json() as { email: string }).email).toBe(accounts[1]!.email)

    await Promise.all([...contexts, anonymous].map(context => context.close()))
  })
})
