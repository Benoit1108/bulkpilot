// @ts-check
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
)
