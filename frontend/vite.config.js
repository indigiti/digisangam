import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

function offlineAssetsPlugin() {
  return {
    name: 'digisangam-offline-assets',
    apply: 'build',
    generateBundle(_options, bundle) {
      // OnGround is eagerly imported from the primary entry for safe offline use.
      // Traverse static imports only: other screens remain lazy-loaded online.
      const files = new Set()
      const pending = Object.values(bundle).filter(item => item.type === 'chunk' && item.isEntry).map(item => item.fileName)
      while (pending.length) {
        const filename = pending.pop()
        if (files.has(filename)) continue
        files.add(filename)
        const chunk = bundle[filename]
        if (chunk?.type !== 'chunk') continue
        for (const imported of chunk.imports || []) pending.push(imported)
        for (const css of chunk.viteMetadata?.importedCss || []) files.add(css)
        for (const asset of chunk.viteMetadata?.importedAssets || []) files.add(asset)
      }
      this.emitFile({
        type: 'asset',
        fileName: 'offline-precache.json',
        source: JSON.stringify({ version: 1, assets: [...files].sort().map(name => '/' + name) }),
      })
    },
  }
}

export default defineConfig({
  base: process.env.DIGISANGAM_BASE || '/digisangam/',
  plugins: [vue(), tailwindcss(), offlineAssetsPlugin()],
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
