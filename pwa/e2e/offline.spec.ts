import type { Page } from '@playwright/test'
import { expect, JSON_HEADERS, test } from './support/fixtures'
import { program } from './support/seed'

/** What the device's offline store holds (services/offline/store.ts): the program followed and its plans. */
function keptOnDevice(page: Page): Promise<{ program: boolean, plans: boolean }> {
  return page.evaluate(async () => {
    const none = { program: false, plans: false }
    // Never opened here before the app made it: opening it would create it empty, without its stores.
    if (!(await indexedDB.databases()).some(database => database.name === 'bulkpilot-offline')) return none
    return new Promise<{ program: boolean, plans: boolean }>((resolve) => {
      const open = indexedDB.open('bulkpilot-offline')
      open.onsuccess = () => {
        const keys = open.result.transaction('values').objectStore('values').getAllKeys()
        keys.onsuccess = () => {
          const all = keys.result.map(String)
          open.result.close()
          resolve({ program: all.includes('training.activeProgram'), plans: all.some(key => key.startsWith('training.plan.')) })
        }
        keys.onerror = () => resolve(none)
      }
      open.onerror = () => resolve(none)
    })
  })
}

/**
 * The PWA without a network (ADR-0024): the shell from the service worker, a workout started and
 * recorded from scratch with no signal (the program was kept ahead of time), sent when the network
 * is back; elsewhere the app says it can't reach the server instead of showing stale data.
 */
test.describe('offline', () => {
  test('a workout started and recorded without a network is sent once back online', async ({ page, context, account: _account }) => {
    await program(page)
    await page.goto('/training')
    await expect(page.getByRole('button', { name: 'Démarrer Full A' })).toBeVisible()
    // The service worker controls the app, and the program and its day's plan are kept on the device.
    await page.evaluate(() => navigator.serviceWorker.ready)
    await expect.poll(() => keptOnDevice(page), { timeout: 10_000 }).toEqual({ program: true, plans: true })

    // In the gym, no signal at all, the app opened afresh.
    await context.setOffline(true)
    await page.reload()
    await expect(page.getByText('Connexion impossible').first()).toBeVisible()
    await page.getByRole('button', { name: 'Démarrer Full A' }).click()
    await expect(page).toHaveURL(/\/training\/seance$/)
    await expect(page.getByRole('heading', { level: 2, name: 'Développé couché à la barre' })).toBeVisible()
    await page.getByLabel('Charge').fill('62,5')
    await page.getByLabel('Répétitions').fill('8')
    await page.getByRole('button', { name: 'Valider la série' }).click()
    await expect(page.getByRole('status').getByText('Hors ligne : tes séries sont gardées sur cet appareil.')).toBeVisible()
    await expect(page.getByRole('list', { name: 'Séries faites' })).toContainText('62,5 kg × 8')

    // Back online: everything recorded is sent, in order, without the user doing anything.
    await context.setOffline(false)
    await expect(page.getByText(/sur cet appareil|attend(ent)? ta reconnexion/)).toHaveCount(0, { timeout: 15_000 })
    const inProgress = await page.request.get('/api/workouts/in-progress', { headers: JSON_HEADERS })
    const [workout] = await inProgress.json() as Array<{ sets: Array<{ loadKg: number | null, reps: number | null }> }>
    expect(workout?.sets).toEqual([expect.objectContaining({ loadKg: 62.5, reps: 8 })])
  })

  test('without a network, the food search says so rather than showing nothing', async ({ page, context, account: _account }) => {
    await page.goto('/nutrition')
    await page.evaluate(() => navigator.serviceWorker.ready)
    await context.setOffline(true)
    await page.getByRole('button', { name: 'Ajouter un aliment au petit-déjeuner' }).click()
    await page.getByRole('dialog').getByRole('textbox', { name: 'Rechercher un aliment' }).fill('riz')
    await expect(page.getByRole('dialog').getByRole('status')).toContainText('La recherche a échoué. Vérifie ta connexion.')
  })
})
