import { beforeEach, describe, expect, it, vi } from 'vitest'
import swSource from '../../public/sw.js?raw'

type Handler = (event: Record<string, unknown>) => void

class FakeCache {
  store = new Map<string, Response>()
  async match(request: Request | string) {
    return this.store.get(typeof request === 'string' ? request : request.url)
  }
  async put(request: Request | string, response: Response) {
    const key = typeof request === 'string' ? request : request.url
    this.store.delete(key)
    this.store.set(key, response)
  }
  async add(url: string) {
    this.store.set(new URL(url, 'https://app.test').href, new Response('shell'))
  }
  async keys() {
    return [...this.store.keys()].map((url) => new Request(url))
  }
  async delete(request: Request | string) {
    return this.store.delete(typeof request === 'string' ? request : request.url)
  }
}

const source = swSource
let handlers: Record<string, Handler>
let caches: Map<string, FakeCache>
let fetchMock: ReturnType<typeof vi.fn>
let skipWaiting: ReturnType<typeof vi.fn>
let claim: ReturnType<typeof vi.fn>

const cacheStore = {
  open: async (name: string) => {
    if (!caches.has(name)) caches.set(name, new FakeCache())
    return caches.get(name)!
  },
  keys: async () => [...caches.keys()],
  delete: async (name: string) => caches.delete(name),
  match: async (url: string, options?: { cacheName: string }) => caches.get(options!.cacheName)?.match(new Request(new URL(url, 'https://app.test'))),
}

beforeEach(() => {
  handlers = {}
  caches = new Map()
  fetchMock = vi.fn()
  skipWaiting = vi.fn()
  claim = vi.fn()
  const self = {
    location: { origin: 'https://app.test' },
    addEventListener: (type: string, handler: Handler) => (handlers[type] = handler),
    skipWaiting,
    clients: { claim },
  }
  new Function('self', 'caches', 'fetch', source)(self, cacheStore, fetchMock)
})

async function dispatch(request: Request): Promise<Response | undefined> {
  let result: Promise<Response> | undefined
  handlers.fetch!({ request, respondWith: (p: Promise<Response>) => (result = p) })
  return result
}
const get = (path: string, init?: RequestInit) => new Request(`https://app.test${path}`, init)
const navigate = () => {
  const request = get('/library')
  Object.defineProperty(request, 'mode', { value: 'navigate' })
  return request
}
async function run(type: string, data?: unknown) {
  const waits: Promise<unknown>[] = []
  handlers[type]!({ data, waitUntil: (p: Promise<unknown>) => waits.push(p) })
  await Promise.all(waits)
}

describe('service worker', () => {
  it('precaches only the shell on install and takes over', async () => {
    await run('install')
    expect([...caches.get('bookshelf-shell-v1')!.store.keys()]).toEqual(['https://app.test/'])
    expect(skipWaiting).toHaveBeenCalled()
  })

  it('deletes old caches on activate and claims clients', async () => {
    caches.set('bookshelf-api-v0', new FakeCache())
    caches.set('unrelated', new FakeCache())
    await cacheStore.open('bookshelf-api-v1')
    await run('activate')
    expect([...caches.keys()].sort()).toEqual(['bookshelf-api-v1', 'unrelated'])
    expect(claim).toHaveBeenCalled()
  })

  it('leaves writes, other APIs, chapters and cross-origin requests alone', async () => {
    for (const request of [
      get('/api/library', { method: 'POST' }),
      get('/api/books'),
      get('/api/reader/1/chapters/2'),
      new Request('https://cdn.test/assets/a.js'),
      get('/other.png'),
    ]) {
      expect(await dispatch(request)).toBeUndefined()
    }
  })

  it('serves the cached shell for a navigation when offline', async () => {
    await run('install')
    fetchMock.mockRejectedValue(new TypeError('offline'))
    const response = await dispatch(navigate())
    expect(await response!.text()).toBe('shell')
  })

  it('prefers the network for navigations', async () => {
    fetchMock.mockResolvedValue(new Response('fresh'))
    expect(await (await dispatch(navigate()))!.text()).toBe('fresh')
  })

  it('serves hashed assets cache-first and bounds the cache', async () => {
    fetchMock.mockImplementation(async () => new Response('js'))
    await (await dispatch(get('/assets/a.js')))
    await dispatch(get('/assets/a.js'))
    expect(fetchMock).toHaveBeenCalledTimes(1)

    for (let i = 0; i < 70; i++) await dispatch(get(`/assets/f${i}.js`))
    const store = caches.get('bookshelf-assets-v1')!.store
    expect(store.size).toBe(60)
    expect(store.has('https://app.test/assets/a.js')).toBe(false)
    expect(store.has('https://app.test/assets/f69.js')).toBe(true)
  })

  it('falls back to the last seen API answer when offline', async () => {
    fetchMock.mockResolvedValueOnce(new Response('{"a":1}', { status: 200 }))
    await dispatch(get('/api/library?status=reading'))
    fetchMock.mockRejectedValueOnce(new TypeError('offline'))
    const response = await dispatch(get('/api/library?status=reading'))
    expect(await response!.text()).toBe('{"a":1}')
  })

  it('never stores non-200 or no-store API responses', async () => {
    fetchMock.mockResolvedValueOnce(new Response('x', { status: 401 }))
    await dispatch(get('/api/me'))
    fetchMock.mockResolvedValueOnce(new Response('x', { headers: { 'Cache-Control': 'private, no-store' } }))
    await dispatch(get('/api/feed'))
    expect(caches.get('bookshelf-api-v1')?.store.size ?? 0).toBe(0)
  })

  it('fails the request when offline and nothing is cached', async () => {
    fetchMock.mockRejectedValue(new TypeError('offline'))
    await expect(dispatch(get('/api/quotes'))).rejects.toThrow('offline')
  })

  it('clears the API cache on request from the page', async () => {
    fetchMock.mockResolvedValue(new Response('x'))
    await dispatch(get('/api/me'))
    await run('message', { type: 'clear-api-cache' })
    expect(caches.has('bookshelf-api-v1')).toBe(false)
    await run('message', { type: 'other' })
  })

  it('does not store an answer whose request started before the cache was cleared', async () => {
    let finish!: (response: Response) => void
    fetchMock.mockReturnValueOnce(new Promise<Response>((resolve) => (finish = resolve)))
    const pending = dispatch(get('/api/me'))
    await run('message', { type: 'clear-api-cache' })
    finish(new Response('{"id":1}'))
    await pending
    expect(caches.get('bookshelf-api-v1')?.store.size ?? 0).toBe(0)
  })
})
