/** Must match API_CACHE in public/sw.js. */
export const API_CACHE_NAME = 'bookshelf-api-v1'

/** Forgets the cached API answers so the next person on this device cannot see them offline. */
export function clearApiCache(): void {
  try {
    navigator.serviceWorker?.controller?.postMessage({ type: 'clear-api-cache' })
  } catch {
    // Fall through to the direct deletion below.
  }
  try {
    void globalThis.caches?.delete(API_CACHE_NAME).catch(() => undefined)
  } catch {
    // No Cache API here (insecure context, old browser): nothing was cached either.
  }
}
