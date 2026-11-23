import type { Router } from 'vue-router'

const KEY = 'bookshelf:chunk-reload'

export function isChunkLoadError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : String(error)

  return /Failed to fetch dynamically imported module|error loading dynamically imported module|Importing a module script failed|Unable to preload CSS/i.test(message)
}

/**
 * After a deploy the hashed chunks of the old build are gone. Reload the page once at the target path so the browser
 * picks up the new build; sessionStorage guards against a reload loop if the chunk is really missing.
 * Returns true when a reload was started.
 */
export function reloadOnce(path: string, reload: (path: string) => void = (p) => window.location.assign(p)): boolean {
  try {
    if (sessionStorage.getItem(KEY) === path) {
      sessionStorage.removeItem(KEY)

      return false
    }
    sessionStorage.setItem(KEY, path)
  } catch {
    // Storage is unavailable: we cannot guard against a loop, so do not reload at all.
    return false
  }

  reload(path)

  return true
}

export function installChunkReload(router: Router): void {
  router.onError((error, to) => {
    if (isChunkLoadError(error)) {
      reloadOnce(to.fullPath)
    }
  })

  window.addEventListener('vite:preloadError', (event) => {
    event.preventDefault()
    reloadOnce(window.location.pathname + window.location.search)
  })
}
