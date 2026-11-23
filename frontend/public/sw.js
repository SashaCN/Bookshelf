/* Minimal offline support: app shell + last seen data of a few read-only API screens. Writes are never touched. */
const VERSION = 'v1'
const SHELL_CACHE = `bookshelf-shell-${VERSION}`
const ASSETS_CACHE = `bookshelf-assets-${VERSION}`
const API_CACHE = `bookshelf-api-${VERSION}`
const MAX_ASSETS = 60
const API_ALLOW = ['/api/me', '/api/library', '/api/quotes', '/api/quotes/daily', '/api/stats/summary', '/api/feed', '/api/reader']

/** Which strategy a request gets; null means "do not touch it". */
function route(request, origin) {
  if (request.method !== 'GET') return null
  const url = new URL(request.url)
  if (url.origin !== origin) return null
  if (request.mode === 'navigate') return 'navigation'
  if (url.pathname.startsWith('/assets/')) return 'asset'
  if (API_ALLOW.includes(url.pathname)) return 'api'
  return null
}

function storable(response) {
  if (!response || response.status !== 200) return false
  return !/no-store/i.test(response.headers.get('Cache-Control') || '')
}

async function trim(cache, max) {
  const keys = await cache.keys()
  for (const key of keys.slice(0, Math.max(0, keys.length - max))) await cache.delete(key)
}

async function navigation(request) {
  try {
    return await fetch(request)
  } catch (error) {
    const cached = await caches.match('/', { cacheName: SHELL_CACHE })
    if (cached) return cached
    throw error
  }
}

async function asset(request) {
  const cache = await caches.open(ASSETS_CACHE)
  const cached = await cache.match(request)
  if (cached) return cached
  const response = await fetch(request)
  if (storable(response)) {
    await cache.put(request, response.clone())
    await trim(cache, MAX_ASSETS)
  }
  return response
}

async function api(request) {
  const cache = await caches.open(API_CACHE)
  try {
    const response = await fetch(request)
    if (storable(response)) await cache.put(request, response.clone())
    return response
  } catch (error) {
    const cached = await cache.match(request)
    if (cached) return cached
    throw error
  }
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(SHELL_CACHE)
      .then((cache) => cache.add('/'))
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  const keep = [SHELL_CACHE, ASSETS_CACHE, API_CACHE]
  event.waitUntil(
    caches
      .keys()
      .then((names) => Promise.all(names.filter((n) => n.startsWith('bookshelf-') && !keep.includes(n)).map((n) => caches.delete(n))))
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  const kind = route(event.request, self.location.origin)
  if (kind === 'navigation') event.respondWith(navigation(event.request))
  else if (kind === 'asset') event.respondWith(asset(event.request))
  else if (kind === 'api') event.respondWith(api(event.request))
})

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'clear-api-cache') event.waitUntil(caches.delete(API_CACHE))
})
