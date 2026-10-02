import { expectAccessible } from './support/accessibility'
import { expect, registerThroughApi, test, uniqueEmail } from './support/fixtures'
import { dayAgo } from './support/seed'

/** A random valid EAN-13 in the in-store range (GS1 prefix 29). */
function inStoreCode(): string {
  const body = `29${String(Math.floor(Math.random() * 1e10)).padStart(10, '0')}`
  const sum = [...body].reverse().reduce((total, digit, index) => total + Number(digit) * (index % 2 === 0 ? 3 : 1), 0)
  return `${body}${(10 - (sum % 10)) % 10}`
}

// Needs the CIQUAL excerpt (api/tests/Fixtures/ciqual), imported by `make e2e` and `make e2e-prod`.
test.describe('nutrition', () => {
  test('targets from the estimate, then a food logged against them', async ({ page, account: _account }) => {
    await page.goto('/nutrition/objectifs')
    await page.getByLabel('Sexe').selectOption('male')
    await page.getByLabel('Date de naissance').fill('1996-01-15')
    await page.getByLabel('Taille').fill('180')
    await page.getByLabel('Poids actuel').fill('80')
    await page.getByLabel('Mode de vie (hors sport)').selectOption('active')
    await page.getByLabel('Séances par semaine').fill('4')
    await page.getByRole('button', { name: 'Calculer' }).click()

    const steps = page.getByRole('list', { name: 'Détail du calcul' })
    await expect(steps).toContainText('Métabolisme de base')
    await expect(steps).toContainText('+275\u00A0kcal')
    await expect(page.getByTestId('macro-energy')).toContainText('Les macros font')
    await page.getByRole('button', { name: 'Enregistrer mes objectifs' }).click()
    await expect(page.getByText('Objectifs enregistrés.')).toBeVisible()
    await expect(page.getByText(/· Estimation/)).toBeVisible()

    await page.getByRole('link', { name: 'Nutrition' }).first().click()
    await expect(page).toHaveURL(/\/nutrition$/)
    await expect(page.getByText('Aujourd’hui', { exact: true })).toBeVisible()
    await page.getByRole('button', { name: 'Ajouter un aliment au déjeuner' }).click()
    const drawer = page.getByRole('dialog', { name: 'Ajouter au déjeuner' })
    await drawer.getByLabel('Rechercher un aliment ou une recette').fill('poulet saute')
    await drawer.getByRole('button', { name: /Poulet, filet, sans peau, sauté\/poêlé/ }).click()
    await drawer.getByLabel('Quantité').fill('150')
    await drawer.getByRole('button', { name: 'Ajouter', exact: true }).click()
    await expect(drawer).toBeHidden()

    const lunch = page.getByTestId('meal-lunch')
    await expect(lunch).toContainText('Poulet, filet, sans peau, sauté/poêlé')
    await expect(lunch).toContainText('150\u00A0g')
    await expect(page.getByTestId('energy-gap')).toContainText('Encore')

    await page.reload()
    await expect(page.getByTestId('meal-lunch')).toContainText('Poulet')
  })

  test('an entry is corrected, moved to another meal, then removed', async ({ page, account: _account }) => {
    await page.goto('/nutrition')
    await page.getByRole('button', { name: 'Ajouter un aliment au petit-déjeuner' }).click()
    const adding = page.getByRole('dialog', { name: 'Ajouter au petit-déjeuner' })
    await adding.getByLabel('Rechercher un aliment ou une recette').fill('oeuf')
    await adding.getByRole('button', { name: /Oeuf, cru/ }).click()
    await adding.getByLabel('Quantité').fill('120')
    await adding.getByRole('button', { name: 'Ajouter', exact: true }).click()
    await expect(page.getByTestId('meal-breakfast')).toContainText('Oeuf, cru')

    await page.getByRole('button', { name: /Modifier Oeuf, cru/ }).click()
    const editing = page.getByRole('dialog', { name: 'Modifier l’entrée' })
    await editing.getByLabel('Quantité').fill('60')
    await editing.getByLabel('Repas').selectOption('snack')
    await editing.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(editing).toBeHidden()
    await expect(page.getByTestId('meal-snack')).toContainText('60\u00A0g')
    await expect(page.getByTestId('meal-breakfast')).not.toContainText('Oeuf')

    await page.getByRole('button', { name: /Modifier Oeuf, cru/ }).click()
    const removing = page.getByRole('dialog', { name: 'Modifier l’entrée' })
    await removing.getByRole('button', { name: 'Supprimer l’entrée' }).click()
    await removing.getByRole('button', { name: 'Confirmer la suppression' }).click()
    await expect(page.getByTestId('meal-snack')).not.toContainText('Oeuf')
    await expect(page.getByRole('status').filter({ hasText: 'retiré' })).toContainText('Oeuf, cru retiré à la collation.')
  })

  test('a custom food is created and found by the search', async ({ page, account: _account }) => {
    await page.goto('/nutrition/aliments')
    await expect(page.getByText('Aucun aliment personnel')).toBeVisible()
    await page.getByRole('link', { name: 'Nouvel aliment' }).click()
    await page.getByLabel('Nom').fill('Skyr nature')
    await page.getByLabel('Marque').fill('Siggi’s')
    await page.getByLabel('Énergie').fill('63')
    await page.getByLabel('Protéines').fill('11')
    await page.getByLabel('Glucides').fill('4')
    await page.getByLabel('Lipides', { exact: true }).fill('0.2')
    await page.getByRole('button', { name: 'Ajouter une portion' }).click()
    await page.getByLabel('Nom de la portion 1').fill('1 pot')
    await page.getByLabel('Poids de la portion 1').fill('150')
    await page.getByRole('button', { name: 'Créer l’aliment' }).click()
    await expect(page.getByRole('link', { name: /Skyr nature/ })).toBeVisible()

    await page.goto('/nutrition')
    await page.getByRole('button', { name: 'Ajouter un aliment au gainer' }).click()
    const drawer = page.getByRole('dialog', { name: 'Ajouter au gainer' })
    await drawer.getByLabel('Rechercher un aliment ou une recette').fill('skyr')
    await drawer.getByRole('button', { name: /Skyr nature/ }).click()
    await expect(drawer.getByLabel('Quantité')).toHaveValue('150')
    await drawer.getByRole('button', { name: 'Ajouter', exact: true }).click()
    await expect(page.getByTestId('meal-gainer')).toContainText('Skyr nature (Siggi’s)')
  })

  test('the meal split must add up to 100 %', async ({ page, account: _account }) => {
    await page.goto('/nutrition/repartition')
    await page.getByLabel('Collation').fill('10')
    await expect(page.getByRole('button', { name: 'Enregistrer' })).toBeDisabled()
    await page.getByLabel('Dîner').fill('20')
    await page.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(page.getByText('Répartition enregistrée.')).toBeVisible()
  })

  test('a recipe is logged by the serving, then the meal is kept as a favourite and added again', async ({ page, account: _account }) => {
    await page.goto('/nutrition/recettes')
    await page.getByRole('link', { name: 'Nouvelle recette' }).click()
    await page.getByLabel('Nom de la recette').fill('Riz au poulet')
    await page.getByLabel('Nombre de portions').fill('4')
    await page.getByLabel('Poids une fois cuit').fill('1100')
    for (const [query, name, grams] of [['riz blanc cru', /Riz blanc, cru/, '300'], ['poulet filet cru', /Poulet, filet, sans peau, cru/, '400']] as const) {
      await page.getByRole('button', { name: 'Ajouter un ingrédient' }).click()
      const picker = page.getByRole('dialog', { name: 'Ajouter un ingrédient' })
      await picker.getByLabel('Rechercher un aliment').fill(query)
      await picker.getByRole('button', { name }).first().click()
      await expect(picker).toBeHidden()
      await page.getByRole('textbox', { name }).fill(grams)
    }
    await page.getByRole('button', { name: 'Créer la recette' }).click()
    await expect(page.getByRole('table')).toContainText('Par portion')
    await expect(page.getByRole('table')).toContainText('275\u00A0g')

    await page.goto('/nutrition')
    await page.getByRole('button', { name: 'Ajouter un aliment au déjeuner' }).click()
    const drawer = page.getByRole('dialog', { name: 'Ajouter au déjeuner' })
    await drawer.getByRole('button', { name: /Riz au poulet/ }).click()
    await expect(drawer.getByLabel('Quantité')).toHaveValue('275')
    await drawer.getByRole('button', { name: 'Ajouter', exact: true }).click()
    await expect(page.getByTestId('meal-lunch')).toContainText('Riz au poulet')

    await page.getByRole('button', { name: 'Enregistrer « Déjeuner » comme repas favori' }).click()
    const saving = page.getByRole('dialog', { name: 'Enregistrer comme repas favori' })
    await saving.getByLabel('Nom du repas').fill('Midi habituel')
    await saving.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(page.getByText('« Midi habituel » est dans tes repas favoris.')).toBeVisible()

    await page.getByRole('button', { name: 'Ajouter un aliment au dîner' }).click()
    await page.getByRole('dialog', { name: 'Ajouter au dîner' }).getByRole('button', { name: 'Ajouter le repas favori Midi habituel' }).click()
    await expect(page.getByTestId('meal-dinner')).toContainText('Riz au poulet')
  })

  test('the meal builder fills what is left of the day, and a proposal is logged in one tap', async ({ page, account: _account }) => {
    const created = await page.request.post('/api/nutrition/targets', {
      data: { effectiveFrom: dayAgo(0), energyKcal: 2500, proteinGrams: 150, carbohydrateGrams: 300, fatGrams: 75 },
      headers: { Accept: 'application/json' },
    })
    expect(created.status(), await created.text()).toBe(201)

    await page.goto('/nutrition')
    await page.getByRole('button', { name: 'Que manger\u202F? Composer un repas' }).click()
    const builder = page.getByRole('dialog', { name: 'Que manger\u202F?' })
    await builder.getByLabel('Composer pour').selectOption('dinner')
    await expect(builder.getByTestId('remaining')).toContainText('750\u00A0kcal')
    const add = builder.getByRole('button', { name: 'Ajouter au dîner' }).first()
    await expect(add).toBeVisible()
    await add.click()
    await expect(builder).toBeHidden()

    await expect(page.getByTestId('meal-dinner').getByRole('listitem')).not.toHaveCount(0)
  })

  test('the nutrition screens have no accessibility violation', async ({ page, account: _account }) => {
    await page.goto('/nutrition')
    await expect(page.getByTestId('meal-lunch')).toBeVisible()
    await expectAccessible(page)

    await page.getByRole('button', { name: 'Ajouter un aliment au déjeuner' }).click()
    await expect(page.getByRole('dialog', { name: 'Ajouter au déjeuner' })).toBeVisible()
    await expectAccessible(page)
    await page.keyboard.press('Escape')

    for (const path of ['/nutrition/objectifs', '/nutrition/repartition', '/nutrition/recettes/nouvelle', '/nutrition/aliments/nouveau']) {
      await page.goto(path)
      await expect(page.getByRole('main')).toBeVisible()
      await expectAccessible(page)
    }
  })

  test('a user far ahead of the server in time logs on their own today', async ({ browser }) => {
    // UTC+14: their today is often the server's tomorrow, which the journal accepts.
    const kiritimati = await browser.newContext({ baseURL: process.env.E2E_BASE_URL ?? 'https://localhost', ignoreHTTPSErrors: true, timezoneId: 'Pacific/Kiritimati' })
    const page = await kiritimati.newPage()
    await registerThroughApi(page, { email: uniqueEmail('kiritimati'), firstName: 'Tabu' })

    await page.goto('/nutrition')
    await page.getByRole('button', { name: 'Ajouter un aliment au dîner' }).click()
    const drawer = page.getByRole('dialog', { name: 'Ajouter au dîner' })
    await drawer.getByLabel('Rechercher un aliment ou une recette').fill('riz blanc cru')
    await drawer.getByRole('button', { name: /Riz blanc, cru/ }).first().click()
    await drawer.getByRole('button', { name: 'Ajouter', exact: true }).click()
    await expect(page.getByTestId('meal-dinner')).toContainText('Riz blanc, cru')
    await kiritimati.close()
  })

  test('a scanned product is confirmed, kept, then logged; an unknown one is typed in', async ({ page, account: _account }) => {
    // FOOD_DATABASE=fake: any in-store code (prefix 29) is a product nobody confirmed yet, so each
    // run scans a new one (confirmed products are shared). No camera in CI: the code is typed.
    const code = inStoreCode()
    const scanned = { code, name: `Produit test ${code}`, serving: '40', logged: `Produit test ${code} (Marque test)` }
    await page.goto('/nutrition')
    await page.getByRole('button', { name: 'Ajouter un aliment au petit-déjeuner' }).click()
    const drawer = page.getByRole('dialog', { name: 'Ajouter au petit-déjeuner' })
    await drawer.getByRole('button', { name: 'Scanner un code-barres' }).click()
    await drawer.getByLabel('Code-barres').fill(scanned.code)
    await drawer.getByRole('button', { name: 'Rechercher ce code' }).click()
    await expect(drawer.getByRole('article')).toContainText(scanned.name)
    await expect(drawer.getByRole('article')).toContainText('Open Food Facts (ODbL)')
    await expect(drawer.getByRole('heading', { name: scanned.name })).toBeFocused()
    await expectAccessible(page)
    await drawer.getByRole('button', { name: 'Valeurs conformes à l’étiquette' }).click()
    await expect(drawer.getByLabel('Quantité')).toHaveValue(scanned.serving)
    await drawer.getByRole('button', { name: 'Ajouter', exact: true }).click()
    await expect(page.getByTestId('meal-breakfast')).toContainText(scanned.logged)

    await page.getByRole('button', { name: 'Ajouter un aliment au déjeuner' }).click()
    const lunch = page.getByRole('dialog', { name: 'Ajouter au déjeuner' })
    await lunch.getByRole('button', { name: 'Scanner un code-barres' }).click()
    await lunch.getByLabel('Code-barres').fill('2000000000046')
    await lunch.getByRole('button', { name: 'Rechercher ce code' }).click()
    await expect(lunch.getByRole('heading', { name: 'Saisir le produit' })).toBeVisible()
    await expect(lunch).toContainText('Produit inconnu d’Open Food Facts')
    await lunch.getByLabel('Nom', { exact: true }).fill('Galettes de maïs')
    await lunch.getByLabel('Énergie').fill('380')
    await lunch.getByLabel('Protéines').fill('8')
    await lunch.getByLabel('Glucides', { exact: true }).fill('80')
    await lunch.getByLabel('Lipides', { exact: true }).fill('3,5')
    await lunch.getByRole('button', { name: 'Enregistrer ce produit' }).click()
    await lunch.getByLabel('Quantité').fill('30')
    await lunch.getByRole('button', { name: 'Ajouter', exact: true }).click()
    await expect(page.getByTestId('meal-lunch')).toContainText('Galettes de maïs')

    // Scanned again, it is known: straight to the quantity.
    await page.getByRole('button', { name: 'Ajouter un aliment au dîner' }).click()
    const dinner = page.getByRole('dialog', { name: 'Ajouter au dîner' })
    await dinner.getByRole('button', { name: 'Scanner un code-barres' }).click()
    await dinner.getByLabel('Code-barres').fill('2000000000046')
    await dinner.getByRole('button', { name: 'Rechercher ce code' }).click()
    await expect(dinner.getByLabel('Quantité')).toBeVisible()
    await expectAccessible(page)
  })
})
