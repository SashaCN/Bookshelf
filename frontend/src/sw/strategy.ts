/** Caches of the app shell are named "bookshelf-shell-<version>"; every deploy has its own version. */
export const SHELL_CACHE_PREFIX = 'bookshelf-shell-'

/** The one HTML page of the single-page app; every screen is a route inside it. */
export const SHELL_URL = '/index.html'

/** Paths answered by the backend: always fetched from the network, never from a cache (they are the reader's data). */
const SERVER_PATHS = ['/api', '/docs', '/up']

export type RequestKind =
  /** Not for the service worker: the browser handles it as if there were no worker. */
  | 'ignore'
  /** Opening a screen: answered with the app shell, so the app starts without a network. */
  | 'navigation'
  /** A built file of the app (script, style, icon): answered from the cache. */
  | 'static'

interface RequestLike {
  method: string
  url: string
  mode: string
}

function isServerPath(pathname: string): boolean {
  return SERVER_PATHS.some((path) => pathname === path || pathname.startsWith(`${path}/`))
}

/** What the service worker does with a request. Only reading the app's own files is ever answered from the cache. */
export function classifyRequest(request: RequestLike, origin: string): RequestKind {
  const url = new URL(request.url)

  if (request.method !== 'GET' || url.origin !== origin || isServerPath(url.pathname)) {
    return 'ignore'
  }

  return request.mode === 'navigate' ? 'navigation' : 'static'
}

export function shellCacheName(version: string): string {
  return `${SHELL_CACHE_PREFIX}${version}`
}

/** The caches of earlier versions, which are of no use once the current version is active. */
export function staleCaches(names: readonly string[], current: string): string[] {
  return names.filter((name) => name.startsWith(SHELL_CACHE_PREFIX) && name !== current)
}
