import { fileURLToPath } from 'node:url'
import { defineVitestProject } from '@nuxt/test-utils/config'
import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    projects: [
      // Plain TypeScript: no Nuxt runtime, fast.
      {
        resolve: { alias: { '~': fileURLToPath(new URL('./app', import.meta.url)) } },
        test: { name: 'unit', include: ['test/unit/**/*.test.ts'], environment: 'node' },
      },
      // Components, composables and plugins inside a Nuxt runtime.
      await defineVitestProject({
        test: {
          name: 'nuxt',
          include: ['test/nuxt/**/*.test.ts'],
          environment: 'nuxt',
          environmentOptions: { nuxt: { domEnvironment: 'happy-dom' } },
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
