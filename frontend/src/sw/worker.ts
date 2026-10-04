import { classifyRequest, SHELL_URL, shellCacheName, staleCaches } from './strategy'

interface ExtendableEventLike {
  waitUntil(promise: Promise<unknown>): void
}

interface MessageEventLike {
  data: unknown
}

interface FetchEventLike {
  request: Request
  respondWith(response: Promise<Response>): void
}

/**
 * What the worker uses of its environment (the global `self` of a service worker). Written out here so that the
 * behaviour can be tested with a fake environment: a service worker cannot be run in a unit test.
 */
export interface WorkerScope {
  location: { origin: string }
  caches: Pick<CacheStorage, 'open' | 'keys' | 'delete' | 'match'>
  clients: { claim(): Promise<void> }
  skipWaiting(): Promise<void>
  fetch(request: Request): Promise<Response>
  addEventListener(type: 'install' | 'activate', listener: (event: ExtendableEventLike) => void): void
  addEventListener(type: 'message', listener: (event: MessageEventLike) => void): void
  addEventListener(type: 'fetch', listener: (event: FetchEventLike) => void): void
}

export interface WorkerOptions {
  /** Changes with every change of the built files; each version has a cache of its own. */
  version: string
  /** Every built file of the app, as an absolute path. */
  precache: readonly string[]
}

/** The app shell: the built files are cached when a version is installed and served from there, even without a network. */
export function registerWorker(scope: WorkerScope, { version, precache }: WorkerOptions): void {
  const cacheName = shellCacheName(version)

  /** Fetches every built file past the browser's HTTP cache, so the shell is one consistent version. */
  async function precacheShell(): Promise<void> {
    const cache = await scope.caches.open(cacheName)

    await Promise.all(
      precache.map(async (path) => {
        const response = await scope.fetch(new Request(new URL(path, scope.location.origin).href, { cache: 'reload' }))

        if (!response.ok) {
          throw new Error(`Could not precache ${path}: ${response.status}`)
        }

        await cache.put(path, response)
      }),
    )
  }

  /** The cached copy of this version, or the network when it is not there. */
  async function fromCache(key: RequestInfo, fallback: Request): Promise<Response> {
    return (await scope.caches.match(key, { cacheName })) ?? scope.fetch(fallback)
  }

  scope.addEventListener('install', (event) => {
    // No skipWaiting() here: a new version waits until the reader agrees to switch (the "update" banner),
    // so a screen with a half-written quote is never reloaded under their hands. The very first install has no
    // earlier version to wait for and becomes active at once.
    event.waitUntil(precacheShell())
  })

  scope.addEventListener('activate', (event) => {
    event.waitUntil(
      (async () => {
        for (const name of staleCaches(await scope.caches.keys(), cacheName)) {
          await scope.caches.delete(name)
        }
        await scope.clients.claim()
      })(),
    )
  })

  scope.addEventListener('message', (event) => {
    if ((event.data as { type?: string } | null)?.type === 'SKIP_WAITING') {
      void scope.skipWaiting()
    }
  })

  scope.addEventListener('fetch', (event) => {
    const kind = classifyRequest(event.request, scope.location.origin)

    if (kind === 'navigation') {
      event.respondWith(fromCache(SHELL_URL, event.request))
    } else if (kind === 'static') {
      event.respondWith(fromCache(event.request, event.request))
    }
  })
}
