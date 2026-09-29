// @ts-check
import tailwind from 'eslint-plugin-better-tailwindcss'
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt(
  {
    // Generated from the API's OpenAPI document (`make openapi`).
    ignores: ['app/types/api.d.ts', 'openapi/**'],
  },
  {
    rules: {
      'vue/no-v-html': 'error',
      'no-console': ['error', { allow: ['warn', 'error'] }],
    },
  },
  {
    // A component past this size does several things: its logic goes to a composable, its parts to components.
    files: ['app/**/*.vue'],
    rules: { 'max-lines': ['error', { max: 250, skipBlankLines: true, skipComments: true }] },
  },
  {
    // Tailwind classes: only ones the theme defines (a typo or a stale token name fails), in one order.
    files: ['app/**/*.{vue,ts}'],
    plugins: { 'better-tailwindcss': tailwind },
    settings: { 'better-tailwindcss': { entryPoint: 'app/assets/css/main.css' } },
    rules: {
      ...tailwind.configs['recommended-error'].rules,
      // Long class lists stay on one line: wrapping them fights the Vue attribute formatting.
      'better-tailwindcss/enforce-consistent-line-wrapping': 'off',
    },
  },
)
