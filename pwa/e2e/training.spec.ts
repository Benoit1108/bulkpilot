import { expectAccessible } from './support/accessibility'
import type { Page } from '@playwright/test'
import { expect, test } from './support/fixtures'

async function exerciseId(page: Page, query: string): Promise<string> {
  const response = await page.request.get(`/api/exercises?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
  const [first] = await response.json() as Array<{ id: string }>
  return first!.id
}

/** A one-day program, straight through the API (its editor has its own test). */
async function program(page: Page): Promise<void> {
  const planned = (id: string, override: Record<string, unknown> = {}) => ({ exerciseId: id, sets: 2, repMin: 8, repMax: 12, targetRir: 2, restSeconds: 60, ...override })
  const response = await page.request.post('/api/programs', {
    headers: { Accept: 'application/json' },
    data: { name: 'Full Body', days: [{ name: 'Full A', blocks: [
      { exercises: [planned(await exerciseId(page, 'developpe couche barre'))] },
      { exercises: [planned(await exerciseId(page, 'elevations laterales halteres'), { sets: 1, restSeconds: 0 }), planned(await exerciseId(page, 'gainage planche'), { sets: 1, repMin: 30, repMax: 60 })] },
    ] }] },
  })
  expect(response.status(), await response.text()).toBe(201)
}

// The exercise library is imported when the API container starts.
test.describe('training', () => {
  test('a program with a superset, then a new version that keeps the first one', async ({ page, account: _account }) => {
    await page.goto('/training')
    await expect(page.getByText('Aucun programme suivi')).toBeVisible()
    await page.getByRole('link', { name: 'Créer un programme' }).click()

    await page.getByLabel('Nom du programme').fill('PPL Masse')
    await page.getByLabel('Nom du jour 1').fill('Push')
    await page.getByRole('button', { name: 'Ajouter un exercice à Push' }).click()
    const picker = page.getByRole('dialog', { name: 'Choisir un exercice' })
    await picker.getByLabel('Rechercher un exercice').fill('developpe couche barre')
    await picker.getByRole('button', { name: /^Développé couché à la barre/ }).click()
    await page.getByRole('button', { name: 'Ajouter un exercice à Push' }).click()
    await picker.getByLabel('Muscle').selectOption('side_delts')
    await picker.getByRole('button', { name: /^Élévations latérales aux haltères/ }).click()
    await page.getByRole('button', { name: /^Faire un superset/ }).nth(1).click()
    await picker.getByLabel('Rechercher un exercice').fill('extension triceps poulie')
    await picker.getByRole('button', { name: /^Extension triceps à la poulie/ }).first().click()
    await expect(page.getByText('Superset B')).toBeVisible()
    await expect(page.getByText('Enchaîné sans repos avec le suivant.')).toBeVisible()
    await expectAccessible(page)
    await page.getByRole('button', { name: 'Créer le programme' }).click()

    await expect(page).toHaveURL(/\/training\/programmes\/[0-9a-f-]{36}$/)
    await expect(page.getByText('Programme suivi · version 1')).toBeVisible()
    const push = page.locator('.day', { has: page.getByRole('heading', { name: 'Push' }) })
    await expect(push).toContainText('3 × 6–10 reps · RIR 2 · repos 3 min')
    await expect(push).toContainText('Superset')

    await page.getByLabel('Séries').first().fill('4')
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click()
    await expect(page.getByText('Version 2 enregistrée. Les séances passées gardent la leur.')).toBeVisible()
    await page.getByRole('link', { name: 'Version 1' }).click()
    await expect(page.getByText(/Version 1 du .* \(la version actuelle est la 2\)/)).toBeVisible()
    await expect(push).toContainText('3 × 6–10 reps')
    await expectAccessible(page)
    await page.getByRole('link', { name: 'Revenir à la version actuelle' }).click()
    await expect(push).toContainText('4 × 6–10 reps')

    await page.goto('/training')
    await expect(page.getByRole('button', { name: 'Démarrer Push' })).toBeVisible()
    await expect(page.getByText('3 exercices')).toBeVisible()
  })

  test('a cue on a library exercise, and an own exercise', async ({ page, account: _account }) => {
    await page.goto('/training/exercices')
    await page.getByLabel('Rechercher un exercice').fill('rowing barre')
    await page.getByRole('link', { name: /^Rowing barre/ }).click()
    await page.getByRole('textbox', { name: 'Consigne' }).fill('Dos plat, tirer vers le nombril')
    await page.getByRole('button', { name: 'Enregistrer la consigne' }).click()
    await expect(page.getByText('Consigne enregistrée.')).toBeVisible()

    await page.goto('/training/exercices/nouveau')
    await page.getByLabel('Nom', { exact: true }).fill('Rowing Yates')
    await page.getByLabel('Muscle principal').selectOption('upper_back')
    await page.getByLabel('Biceps').check()
    await page.getByRole('button', { name: 'Créer l’exercice' }).click()
    await expect(page.getByRole('heading', { name: 'Rowing Yates' })).toBeVisible()
    await expect(page.getByText('Muscles secondairesBiceps')).toBeVisible()
    await expectAccessible(page)

    await page.goto('/training/exercices')
    await page.getByLabel('Rechercher un exercice').fill('rowing')
    await expect(page.getByRole('link', { name: /Rowing Yates.*perso/ })).toBeVisible()
    await expect(page.getByRole('link', { name: /Rowing barre.*consigne/ })).toBeVisible()
  })

  test('a workout set by set, through a lost connection and a reload, then in the history', async ({ page, context, account: _account }) => {
    await program(page)
    await page.goto('/training')
    await page.getByRole('button', { name: 'Démarrer Full A' }).click()
    await expect(page).toHaveURL(/\/training\/seance$/)
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Full A')
    await expect(page.getByRole('heading', { level: 2, name: 'Développé couché à la barre' })).toBeVisible()
    await expectAccessible(page)

    await page.getByLabel('Charge').fill('60')
    await page.getByLabel('Répétitions').fill('10')
    await page.getByRole('group', { name: /^RIR/ }).getByText('2', { exact: true }).click()
    const sent = page.waitForResponse(response => response.request().method() === 'PUT' && response.url().includes('/sets/'))
    await page.getByRole('button', { name: 'Valider la série' }).click()
    expect((await sent).status()).toBe(204)
    const rest = page.getByRole('region', { name: 'Repos' })
    await expect(rest).toBeVisible()
    await expect(page.getByRole('list', { name: 'Séries faites' })).toContainText('60 kg × 10 · RIR 2')
    await expectAccessible(page)
    await rest.getByRole('button', { name: 'Passer' }).click()

    // The gym has no signal: the set is kept on the device.
    await context.setOffline(true)
    await page.getByRole('button', { name: 'Une répétition de plus' }).click()
    await page.getByRole('button', { name: 'Valider la série' }).click()
    await expect(page.getByText('1 modification gardée sur le téléphone, envoyée au retour du réseau.')).toBeVisible()
    await expect(page.getByRole('heading', { level: 2, name: 'Élévations latérales aux haltères' })).toBeVisible()
    await context.setOffline(false)
    await expect(page.getByText('gardée sur le téléphone')).toBeHidden()

    await page.reload()
    await expect(page.getByRole('heading', { level: 2, name: 'Élévations latérales aux haltères' }), 'the workout is still on the device').toBeVisible()
    await page.getByLabel('Charge').fill('10')
    await page.getByLabel('Répétitions').fill('15')
    await page.getByRole('button', { name: 'Valider la série' }).click()
    await expect(page.getByRole('heading', { level: 2, name: 'Gainage (planche)' }), 'straight on in the superset').toBeVisible()

    await page.getByRole('button', { name: 'Terminer', exact: true }).click()
    const finishing = page.getByRole('dialog', { name: 'Terminer la séance' })
    await expect(finishing).toContainText('Gainage (planche)')
    await expectAccessible(page)
    await finishing.getByRole('button', { name: 'Terminer quand même' }).click()
    await expect(page).toHaveURL(/\/training\/seances\/[0-9a-f-]{36}$/)
    await expect(page.getByRole('region', { name: 'Développé couché à la barre' })).toContainText('60 kg × 11 · RIR 2')
    await expect(page.getByRole('region', { name: 'Gainage (planche)' })).toContainText('Pas fait.')
    await expectAccessible(page)

    await page.goto('/training/seances')
    await expect(page.getByRole('link', { name: /^Full A/ })).toContainText('2 exercices · 3 séries')
    await page.goto('/training')
    await page.getByRole('button', { name: 'Démarrer Full A' }).click()
    await expect(page.getByText('Dernière fois')).toBeVisible()
    await expect(page.getByText(/^Suggestion/)).toBeVisible()
  })
})
