// https://nuxt.com/docs/api/configuration/nuxt-config
import tailwindcss from '@tailwindcss/vite'
import { THEME_COLORS } from './app/features/shell/theme'

const appName = process.env.NUXT_PUBLIC_APP_NAME ?? 'BulkPilot'
const themeColor = THEME_COLORS.dark

export default defineNuxtConfig({
  modules: ['@nuxt/eslint', '@vite-pwa/nuxt', '@nuxt/test-utils/module'],

  // Installable PWA used offline in the gym: a client-rendered SPA keeps the app shell
  // cacheable and avoids forwarding session cookies through an SSR server (see ADR-005).
  ssr: false,

  devtools: { enabled: true },

  app: {
    head: {
      // No data-theme here: it would override the stored choice applied by theme-init.js.
      htmlAttrs: { lang: 'fr' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, viewport-fit=cover' },
        { name: 'theme-color', content: themeColor },
        { name: 'color-scheme', content: 'dark light' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        // Content extends under the iOS status bar (white icons); the light theme paints a dark strip behind it.
        { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.ico', sizes: '48x48' },
        { rel: 'icon', href: '/icons/icon.svg', type: 'image/svg+xml' },
        { rel: 'apple-touch-icon', href: '/icons/apple-touch-icon-180x180.png' },
      ],
      // Applied before first paint so the stored theme never flashes.
      script: [{ src: '/theme-init.js' }],
    },
  },

  css: ['@fontsource-variable/inter', '~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      appName,
    },
  },
  compatibilityDate: '2026-09-01',

  vite: {
    plugins: [tailwindcss()],
    server: {
      // The dev server is reached through Caddy (https://localhost), not directly.
      allowedHosts: true,
      ws: {
        protocol: 'wss',
        clientPort: 443,
      },
    },
  },

  typescript: {
    strict: true,
  },

  eslint: {
    config: {
      stylistic: true,
    },
  },

  pwa: {
    registerType: 'prompt',
    manifest: {
      name: appName,
      short_name: appName,
      id: '/',
      description: 'Nutrition, prise de masse, entraînement et progression',
      lang: 'fr',
      start_url: '/',
      scope: '/',
      display: 'standalone',
      orientation: 'portrait',
      background_color: themeColor,
      theme_color: themeColor,
      icons: [
        { src: '/icons/pwa-192x192.png', sizes: '192x192', type: 'image/png' },
        { src: '/icons/pwa-512x512.png', sizes: '512x512', type: 'image/png' },
        { src: '/icons/maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
      ],
    },
    workbox: {
      navigateFallback: '/',
      navigateFallbackDenylist: [/^\/api/, /^\/_profiler/, /^\/_wdt/],
      globPatterns: ['**/*.{js,css,html,svg,png,ico,woff2}'],
      // Only the latin subsets of Inter are ever used by French UI copy.
      globIgnores: ['**/inter-{cyrillic,cyrillic-ext,greek,greek-ext,vietnamese}-*.woff2'],
      // API responses are personal data: never served from a cache. Workbox matches routes on
      // the full URL, hence a function rather than a path RegExp.
      runtimeCaching: [{
        urlPattern: ({ url, sameOrigin }) => sameOrigin && url.pathname.startsWith('/api/'),
        handler: 'NetworkOnly',
      }, {
        // The barcode reader for browsers without BarcodeDetector (~1 MB): fetched on first use
        // only, then kept (its hashed name changes with each version).
        urlPattern: ({ url, sameOrigin }) => sameOrigin && url.pathname.endsWith('.wasm'),
        handler: 'CacheFirst',
        options: { cacheName: 'wasm', expiration: { maxEntries: 4 } },
      }],
    },
    client: {
      // Installed PWAs can stay open for days: look for a new version every hour.
      periodicSyncForUpdates: 3600,
    },
    devOptions: {
      enabled: false,
    },
  },
})
