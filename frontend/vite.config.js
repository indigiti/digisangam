import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  base: process.env.DIGISANGAM_BASE || '/digisangam/',
  plugins: [vue(), tailwindcss()],
  build: { outDir: '../public', emptyOutDir: false },
  server: {
    port: 5173,
    proxy: {
      '/digisangam/api': {
        target: 'http://localhost:8080',
        rewrite: path => path.replace(/^\/digisangam/, ''),
      },
    },
  },
})
