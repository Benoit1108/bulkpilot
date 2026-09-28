import { defineConfig, devices } from '@playwright/test'

// E2E runs against the full Docker stack (Caddy + FrankenPHP + Nuxt + PostgreSQL).
const baseURL = process.env.E2E_BASE_URL ?? 'https://localhost'

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  // One retry keeps a trace of the first failure, but a test that only passes on retry still fails the run.
  retries: process.env.CI ? 1 : 0,
  failOnFlakyTests: !!process.env.CI,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL,
    // The stack under test uses Caddy's internal CA. Service workers ignore `ignoreHTTPSErrors`,
    // so Chromium itself must accept the certificate for the offline tests.
    ignoreHTTPSErrors: true,
    launchOptions: { args: ['--ignore-certificate-errors'] },
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
  ],
})
