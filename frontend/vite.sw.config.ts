import { createHash } from 'node:crypto'
import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'

const dist = fileURLToPath(new URL('./dist', import.meta.url))
const worker = 'sw.js'

function listFiles(directory: string, prefix = ''): string[] {
  return readdirSync(join(directory, prefix), { withFileTypes: true }).flatMap((entry) => {
    const path = prefix ? `${prefix}/${entry.name}` : entry.name
    return entry.isDirectory() ? listFiles(directory, path) : [path]
  })
}

if (!existsSync(join(dist, 'index.html'))) {
  throw new Error('dist/index.html is missing: run "vite build" before building the service worker.')
}

// Everything the app consists of except the worker itself. The version is derived from the contents, so it changes
// exactly when something the reader would see changes.
const files = listFiles(dist)
  .filter((file) => file !== worker)
  .sort()
const version = createHash('sha1')
  .update(files.map((file) => `${file}\n${readFileSync(join(dist, file)).toString('base64')}`).join('\n'))
  .digest('hex')
  .slice(0, 12)

export default defineConfig({
  publicDir: false,
  define: {
    __PRECACHE__: JSON.stringify(files.map((file) => `/${file}`)),
    __VERSION__: JSON.stringify(version),
  },
  build: {
    outDir: 'dist',
    // The app is already in dist; only the worker is added.
    emptyOutDir: false,
    target: 'es2022',
    lib: { entry: 'src/sw/sw.ts', formats: ['iife'], name: 'bookshelfServiceWorker', fileName: () => worker },
  },
})
