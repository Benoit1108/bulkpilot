import type { Page } from '@playwright/test'
import { expectAccessible } from './support/accessibility'
import { expect, test } from './support/fixtures'

const JSON_HEADERS = { Accept: 'application/json' }

/** A finished workout with one set, straight through the API (the workout page has its own test). */
async function workout(page: Page, exerciseId: string, startedAt: Date, loadKg: number): Promise<string> {
  const id = crypto.randomUUID()
  const at = (minutes: number) => new Date(startedAt.getTime() + minutes * 60_000).toISOString()
  const calls: Array<[string, string, Record<string, unknown>]> = [
    ['put', `/api/workouts/${id}`, { startedAt: at(0) }],
    ['put', `/api/workouts/${id}/exercises/0`, { exerciseId }],
    ['put', `/api/workouts/${id}/sets/${crypto.randomUUID()}`, { exerciseIndex: 0, kind: 'normal', loadKg, reps: 8, doneAt: at(10) }],
    ['post', `/api/workouts/${id}/finish`, { finishedAt: at(45) }],
  ]
  for (const [method, url, data] of calls) {
    const response = await page.request[method as 'put' | 'post'](url, { data, headers: JSON_HEADERS })
    expect(response.ok(), await response.text()).toBe(true)
  }
  return id
}

test.describe('notifications', () => {
  test('a new account has none yet, and chooses what it is told and when', async ({ page, account: _account }) => {
    await page.goto('/notifications')
    await expect(page.getByText('Aucune notification')).toBeVisible()
    await expectAccessible(page)

    await page.getByRole('link', { name: 'Réglages des notifications' }).click()
    await expect(page).toHaveURL(/\/notifications\/reglages$/)
    await expect(page.getByLabel('Records')).toBeChecked()
    await expect(page.getByLabel('À partir de')).toHaveValue('23:00')
    await expect(page.getByLabel('Jusqu’à')).toHaveValue('08:00')
    await expectAccessible(page)

    await page.getByLabel('Records').uncheck()
    await page.getByLabel('À partir de').fill('22:30')
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click()
    await expect(page.getByText('Réglages des notifications enregistrés.')).toBeVisible()
    await page.reload()
    await expect(page.getByLabel('Records')).not.toBeChecked()
    await expect(page.getByLabel('À partir de')).toHaveValue('22:30')

    await page.getByLabel('Activer les horaires silencieux').uncheck()
    await expect(page.getByLabel('À partir de')).toHaveCount(0)
    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click()
    await expect(page.getByText('Réglages des notifications enregistrés.')).toBeVisible()
    await page.reload()
    await expect(page.getByLabel('Activer les horaires silencieux')).not.toBeChecked()
    await expectAccessible(page)

    // Reached from the app's settings too.
    await page.goto('/reglages')
    await page.getByRole('main').getByRole('link', { name: /Notifications/ }).click()
    await expect(page).toHaveURL(/\/notifications\/reglages$/)
  })
})

test.describe('a notification', () => {
  test('a record beaten: the bell counts it, opening it leads to the session and reads it', async ({ page, account: _account }) => {
    // Whatever the hour of the run: no quiet hours.
    const settings = await page.request.get('/api/notifications/settings', { headers: JSON_HEADERS })
    const saved = await page.request.put('/api/notifications/settings', { data: { ...(await settings.json()), quietFrom: null, quietTo: null }, headers: JSON_HEADERS })
    expect(saved.ok(), await saved.text()).toBe(true)
    const exercises = await page.request.get('/api/exercises?q=developpe%20couche%20barre', { headers: JSON_HEADERS })
    const [bench] = await exercises.json() as Array<{ id: string }>
    await workout(page, bench!.id, new Date(Date.now() - 3 * 24 * 3600_000), 60)
    const record = await workout(page, bench!.id, new Date(Date.now() - 2 * 3600_000), 70)

    // Opening the app runs the checks.
    await page.goto('/')
    const bell = page.getByRole('link', { name: 'Notifications, 1 non lue' })
    await expect(bell).toBeVisible()
    await bell.click()
    const item = page.getByRole('main').getByRole('listitem').filter({ hasText: 'Non lue' })
    await expect(item).toHaveCount(1)
    await expectAccessible(page)
    await item.getByRole('button').click()
    await expect(page).toHaveURL(new RegExp(`/training/seances/${record}$`))
    await expect(page.getByRole('link', { name: 'Notifications', exact: true })).toBeVisible()
  })
})

test.describe('the device\'s time zone', () => {
  test.use({ timezoneId: 'Asia/Tokyo' })

  test('is told to the server once signed in', async ({ page, account: _account }) => {
    await page.goto('/')
    await expect.poll(async () => (await (await page.request.get('/api/me', { headers: JSON_HEADERS })).json() as { timeZone: string }).timeZone).toBe('Asia/Tokyo')
  })
})
