import { fileURLToPath } from 'node:url'
import { defineVitestProject } from '@nuxt/test-utils/config'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    projects: [
      // Plain TypeScript: no Nuxt runtime, fast.
      {
        resolve: { alias: { '~': fileURLToPath(new URL('./app', import.meta.url)) } },
        // Away from UTC: local-day logic is only tested for real in a time zone that differs.
        test: { name: 'unit', include: ['test/unit/**/*.test.ts'], environment: 'node', env: { TZ: 'Pacific/Honolulu' }, restoreMocks: true, unstubGlobals: true },
      },
      // Components, composables and plugins inside a Nuxt runtime.
      await defineVitestProject({
        test: {
          name: 'nuxt',
          include: ['test/nuxt/**/*.test.ts'],
          setupFiles: ['test/nuxt/setup.ts'],
          environment: 'nuxt',
          environmentOptions: { nuxt: { domEnvironment: 'happy-dom' } },
          // Spies and stubbed globals never leak from one test into the next.
          restoreMocks: true,
          unstubGlobals: true,
        },
      }),
    ],
    coverage: {
      provider: 'v8',
      include: ['app/**/*.{ts,vue}'],
      exclude: ['app/types/**', 'app/error.vue'],
      reporter: ['text-summary', 'lcov'],
      thresholds: {
        'lines': 80,
        'app/services/**': { lines: 95, branches: 90 },
        'app/features/**': { lines: 90 },
        'app/composables/**': { lines: 90 },
      },
    },
  },
})
