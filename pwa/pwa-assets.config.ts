import { defineConfig, minimal2023Preset } from '@vite-pwa/assets-generator/config'

// Regenerate with `pnpm pwa:icons` after changing public/icons/icon*.svg.
// icon.svg → favicon and "any" icons (transparent). Maskable and Apple icons come from
// pwa-assets.maskable.config.ts (full-bleed artwork, no extra padding).
export default defineConfig({
  headLinkOptions: { preset: '2023' },
  preset: {
    ...minimal2023Preset,
    transparent: { ...minimal2023Preset.transparent, sizes: [192, 512] },
    maskable: { sizes: [] },
    apple: { sizes: [] },
  },
  images: ['public/icons/icon.svg'],
})
