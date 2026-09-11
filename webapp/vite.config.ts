import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig } from 'vitest/config';

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['onesalez-logo.png', 'app-icon.svg'],
      manifest: {
        name: 'ONESALEZ Service CRM',
        short_name: 'ONESALEZ',
        description: 'Client service and support tracking for ONESALEZ.',
        theme_color: '#0b1f3a',
        background_color: '#f5f7fb',
        display: 'standalone',
        start_url: '/',
        icons: [
          {
            src: 'app-icon.svg',
            sizes: 'any',
            type: 'image/svg+xml',
            purpose: 'any',
          },
        ],
      },
      workbox: {
        // Always ask the server for the current HTML instead of serving an old app shell.
        globIgnores: ['**/index.html'],
        navigateFallback: undefined,
        cleanupOutdatedCaches: true,
        runtimeCaching: [
          {
            urlPattern: ({ url }) => /^\/(api\/|reset-password(?:\/|$)|login(?:\/|$))/.test(url.pathname),
            handler: 'NetworkOnly',
          },
          {
            urlPattern: ({ request }) => request.mode === 'navigate',
            handler: 'NetworkFirst',
            options: { cacheName: 'onesalez-pages-v2', networkTimeoutSeconds: 5, expiration: { maxEntries: 20 } },
          },
        ],
      },
    }),
  ],
  test: {
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    include: ['src/**/*.test.{ts,tsx}'],
    css: true,
  },
});
