import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { fileURLToPath } from 'node:url'

export default defineConfig({
  base: './',
  plugins: [react()],
  server: {
    origin: 'http://localhost:5173',
    cors: {
      origin: /^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/,
    },
  },
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rolldownOptions: {
      input: fileURLToPath(new URL('./app/src/main.tsx', import.meta.url)),
    },
  },
  publicDir: false,
})
