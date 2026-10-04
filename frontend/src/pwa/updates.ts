import { ref } from 'vue'
import { SHELL_CACHE_PREFIX } from '@/sw/strategy'

/** A new version of the app has been downloaded and waits for the reader to switch to it. */
export const updateReady = ref(false)

let waitingWorker: ServiceWorker | null = null
let applying = false

/**
 * Notices when a new version of the service worker is installed and waiting. The first install has no controller yet,
 * so there is nothing to wait for and nothing to announce: the worker just takes over.
 */
export function watchForUpdates(registration: ServiceWorkerRegistration, container: ServiceWorkerContainer): void {
  const announce = (worker: ServiceWorker) => {
    waitingWorker = worker
    updateReady.value = true
  }

  if (registration.waiting && container.controller) {
    announce(registration.waiting)
  }

  registration.addEventListener('updatefound', () => {
    const worker = registration.installing

    worker?.addEventListener('statechange', () => {
      if (worker.state === 'installed' && container.controller) {
        announce(worker)
      }
    })
  })

  container.addEventListener('controllerchange', () => {
    // Only a switch the reader asked for reloads the page; the very first claim of a fresh install must not.
    if (applying) {
      window.location.reload()
    }
  })
}

/** Switches to the waiting version; the page reloads by itself once the new worker has taken over. */
export function applyUpdate(): void {
  if (!waitingWorker) {
    return
  }

  applying = true
  waitingWorker.postMessage({ type: 'SKIP_WAITING' })
}

/**
 * In development a worker left behind on the same address by an earlier production build would keep serving that old
 * build instead of the dev server, so it is removed together with its caches.
 */
async function removeServiceWorkers(): Promise<void> {
  try {
    for (const registration of await navigator.serviceWorker.getRegistrations()) {
      await registration.unregister()
    }

    for (const name of await caches.keys()) {
      if (name.startsWith(SHELL_CACHE_PREFIX)) {
        await caches.delete(name)
      }
    }
  } catch {
    // There was nothing to clean up, or the browser would not let us.
  }
}

/** Registers the worker in a production build and keeps looking for new versions while the app is open. */
export function registerServiceWorker(): void {
  if (!('serviceWorker' in navigator)) {
    return
  }

  if (!import.meta.env.PROD) {
    void removeServiceWorkers()
    return
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/sw.js')
      .then((registration) => {
        watchForUpdates(registration, navigator.serviceWorker)

        // An installed app can stay open for days: look for a new version whenever it comes back to the screen.
        document.addEventListener('visibilitychange', () => {
          if (document.visibilityState === 'visible') {
            registration.update().catch(() => undefined)
          }
        })
      })
      .catch((error: unknown) => {
        console.warn('The service worker could not be registered', error)
      })
  })
}
