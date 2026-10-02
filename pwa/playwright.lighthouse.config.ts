import { defineConfig } from '@playwright/test'

// Lighthouse on the production image (`make lighthouse`): one page at a time, so that scores
// are not skewed by other tests sharing the machine.
export default defineConfig({
  testDir: './lighthouse',
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  timeout: 600_000,
  // Not the E2E run's folder: one run never wipes the other's traces.
  outputDir: 'test-results/lighthouse',
  reporter: process.env.CI ? [['github'], ['list']] : 'list',
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'https://localhost',
    ignoreHTTPSErrors: true,
  },
})
