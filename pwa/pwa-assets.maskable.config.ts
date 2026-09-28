import { defineConfig } from '@vite-pwa/assets-generator/config'

// Full-bleed icons: the OS applies its own mask; the glyph already sits in the 80% safe zone.
export default defineConfig({
  preset: {
    transparent: { sizes: [], favicons: [] },
    maskable: { sizes: [512], padding: 0 },
    apple: { sizes: [180], padding: 0 },
  },
  images: ['public/icons/icon-maskable.svg'],
})
