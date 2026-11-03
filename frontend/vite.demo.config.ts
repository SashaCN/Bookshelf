import { fileURLToPath, URL } from 'node:url'
import tailwindcss from '@tailwindcss/vite'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import { viteSingleFile } from 'vite-plugin-singlefile'

// A standalone build of the app with an in-browser fake API: one HTML file that opens from disk, no server needed.
export default defineConfig({
  plugins: [vue(), tailwindcss(), viteSingleFile()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  // Hash routing is what lets the page work from file:// where there is no server to answer deep links.
  define: { 'import.meta.env.VITE_DEMO': 'true' },
  build: {
    outDir: 'dist-demo',
    emptyOutDir: true,
    rollupOptions: { input: 'demo.html' },
  },
})
