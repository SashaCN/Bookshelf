import { beforeEach, describe, expect, it, vi } from 'vitest'
import { registerWorker, type WorkerScope } from './worker'

const ORIGIN = 'https://bookshelf.example'
const PRECACHE = ['/index.html', '/assets/app-1.js', '/assets/app-1.css', '/manifest.webmanifest']

function pathOf(key: RequestInfo | URL): string {
  return new URL(typeof key === 'string' ? key : key instanceof URL ? key.href : key.url, ORIGIN).pathname
}

/** An in-memory stand-in for CacheStorage with the few methods the worker uses. */
class FakeCaches {
  readonly stores = new Map<string, Map<string, Response>>()

  async open(name: string) {
    const store = this.stores.get(name) ?? new Map<string, Response>()
    this.stores.set(name, store)

    return { put: async (key: RequestInfo | URL, response: Response) => void store.set(pathOf(key), response) } as unknown as Cache
  }

  async keys() {
    return [...this.stores.keys()]
  }

  async delete(name: string) {
    return this.stores.delete(name)
  }

  async match(key: RequestInfo | URL, options?: { cacheName?: string }) {
    const stores = options?.cacheName ? [this.stores.get(options.cacheName)] : [...this.stores.values()]

    for (const store of stores) {
      const hit = store?.get(pathOf(key))
      if (hit) return hit.clone()
    }

    return undefined
  }
}

type Listener = (event: never) => void

function createEnvironment(served: Record<string, string> = {}) {
  const listeners = new Map<string, Listener[]>()
  const caches = new FakeCaches()
  const network = vi.fn(async (request: Request) => {
    const body = served[new URL(request.url).pathname]
    return body === undefined ? new Response('missing', { status: 404 }) : new Response(body)
  })

  const scope: WorkerScope = {
    location: { origin: ORIGIN },
    caches: caches as unknown as WorkerScope['caches'],
    clients: { claim: vi.fn().mockResolvedValue(undefined) },
    skipWaiting: vi.fn().mockResolvedValue(undefined),
    fetch: network,
    addEventListener: ((type: string, listener: Listener) => {
      listeners.set(type, [...(listeners.get(type) ?? []), listener])
    }) as WorkerScope['addEventListener'],
  }

  function dispatch(type: string, event: object) {
    for (const listener of listeners.get(type) ?? []) listener(event as never)
  }

  /** Runs the install or activate event and waits for what the worker asked to wait for. */
  async function lifecycle(type: 'install' | 'activate') {
    const pending: Promise<unknown>[] = []
    dispatch(type, { waitUntil: (promise: Promise<unknown>) => pending.push(promise) })
    await Promise.all(pending)
  }

  /** Asks the worker for a request; undefined means it left the request to the browser. */
  async function request(path: string, init: { method?: string; mode?: RequestMode } = {}) {
    let response: Promise<Response> | undefined
    const request = new Request(`${ORIGIN}${path}`, { method: init.method ?? 'GET' })
    Object.defineProperty(request, 'mode', { value: init.mode ?? 'cors' })

    dispatch('fetch', { request, respondWith: (promise: Promise<Response>) => (response = promise) })

    return response && (await response).text()
  }

  return { scope, caches, network, dispatch, lifecycle, request, listeners }
}

const served = {
  '/index.html': '<html>shell v1</html>',
  '/assets/app-1.js': 'script',
  '/assets/app-1.css': 'style',
  '/manifest.webmanifest': '{}',
}

describe('the service worker', () => {
  let env: ReturnType<typeof createEnvironment>

  beforeEach(() => {
    env = createEnvironment(served)
    registerWorker(env.scope, { version: 'v1', precache: PRECACHE })
  })

  describe('installing', () => {
    it('keeps every built file in the cache of its version', async () => {
      await env.lifecycle('install')

      const cached = env.caches.stores.get('bookshelf-shell-v1')
      expect([...(cached?.keys() ?? [])].sort()).toEqual([...PRECACHE].sort())
    })

    it('fetches the files past the HTTP cache of the browser, from the same site', async () => {
      await env.lifecycle('install')

      for (const [request] of env.network.mock.calls) {
        expect(request.cache).toBe('reload')
        expect(new URL(request.url).origin).toBe(ORIGIN)
      }
      expect(env.network).toHaveBeenCalledTimes(PRECACHE.length)
    })

    it('fails, and so leaves the old version in charge, when a file cannot be fetched', async () => {
      const broken = createEnvironment({ ...served, '/assets/app-1.js': undefined as unknown as string })
      registerWorker(broken.scope, { version: 'v1', precache: PRECACHE })

      await expect(broken.lifecycle('install')).rejects.toThrow('Could not precache /assets/app-1.js: 404')
    })

    it('does not take over from the running version by itself', async () => {
      await env.lifecycle('install')

      expect(env.scope.skipWaiting).not.toHaveBeenCalled()
    })
  })

  describe('activating', () => {
    it('removes the caches of earlier versions, and only those of this app', async () => {
      await env.caches.open('bookshelf-shell-v0')
      await env.caches.open('another-app')
      await env.lifecycle('install')

      await env.lifecycle('activate')

      expect([...env.caches.stores.keys()].sort()).toEqual(['another-app', 'bookshelf-shell-v1'])
    })

    it('takes control of the open pages', async () => {
      await env.lifecycle('activate')

      expect(env.scope.clients.claim).toHaveBeenCalledTimes(1)
    })
  })

  describe('switching to a waiting version', () => {
    it('does it when the reader has agreed', () => {
      env.dispatch('message', { data: { type: 'SKIP_WAITING' } })

      expect(env.scope.skipWaiting).toHaveBeenCalledTimes(1)
    })

    it.each([{ type: 'SOMETHING_ELSE' }, null, 'SKIP_WAITING', undefined])('ignores the message %j', (data) => {
      env.dispatch('message', { data })

      expect(env.scope.skipWaiting).not.toHaveBeenCalled()
    })
  })

  describe('answering requests', () => {
    beforeEach(async () => {
      await env.lifecycle('install')
      env.network.mockClear()
    })

    it('opens any screen of the app from the cached shell, without the network', async () => {
      expect(await env.request('/', { mode: 'navigate' })).toBe('<html>shell v1</html>')
      expect(await env.request('/library/3', { mode: 'navigate' })).toBe('<html>shell v1</html>')
      expect(await env.request('/quotes/new?text=Hi', { mode: 'navigate' })).toBe('<html>shell v1</html>')
      expect(env.network).not.toHaveBeenCalled()
    })

    it('serves the built files from the cache', async () => {
      expect(await env.request('/assets/app-1.js', { mode: 'no-cors' })).toBe('script')
      expect(await env.request('/manifest.webmanifest')).toBe('{}')
      expect(env.network).not.toHaveBeenCalled()
    })

    it('asks the network for a file that was not cached', async () => {
      const outside = createEnvironment({ ...served, '/favicon.ico': 'icon' })
      registerWorker(outside.scope, { version: 'v1', precache: PRECACHE })
      await outside.lifecycle('install')

      expect(await outside.request('/favicon.ico')).toBe('icon')
    })

    it('leaves the data of the reader to the browser and the server', async () => {
      for (const path of ['/api/me', '/api/library', '/api/sanctum/csrf-cookie', '/docs', '/up']) {
        expect(await env.request(path)).toBeUndefined()
        expect(await env.request(path, { mode: 'navigate' })).toBeUndefined()
      }
    })

    it('leaves writes alone', async () => {
      expect(await env.request('/assets/app-1.js', { method: 'POST' })).toBeUndefined()
    })

    it('never serves a file cached by another version', async () => {
      const old = await env.caches.open('bookshelf-shell-v0')
      await old.put('/assets/old-only.js', new Response('old'))

      // Not in the cache of this version, so the worker goes to the network, which does not have it either.
      expect(await env.request('/assets/old-only.js', { mode: 'no-cors' })).toBe('missing')
    })
  })
})
