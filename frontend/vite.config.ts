/// <reference types="vitest/config" />
import path from 'node:path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: { '@': path.resolve(import.meta.dirname, './src') },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    // inotify nie przechodzi przez bind-mount Windowsa, wiec bez pollingu
    // watcher nie zobaczy zadnej zmiany w plikach.
    watch: { usePolling: true, interval: 300 },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      include: ['src/**/*.{ts,tsx}'],
      // Testy, atrapy i wygenerowane komponenty shadcn/ui nie sa kodem,
      // ktorego pokrycie cokolwiek mowi.
      exclude: ['src/test/**', 'src/**/*.test.{ts,tsx}', 'src/components/ui/**', 'src/main.tsx'],
    },
  },
})
