import { describe, expect, it } from 'vitest'
import { classifyRequest, shellCacheName, staleCaches } from './strategy'

const ORIGIN = 'https://bookshelf.example'

function request(path: string, init: { method?: string; mode?: string } = {}) {
  return { method: init.method ?? 'GET', mode: init.mode ?? 'cors', url: `${ORIGIN}${path}` }
}

describe('classifyRequest', () => {
  it('answers opening a screen with the app shell', () => {
    expect(classifyRequest(request('/library', { mode: 'navigate' }), ORIGIN)).toBe('navigation')
    expect(classifyRequest(request('/quotes/5/edit?x=1', { mode: 'navigate' }), ORIGIN)).toBe('navigation')
    expect(classifyRequest(request('/', { mode: 'navigate' }), ORIGIN)).toBe('navigation')
  })

  it('answers the built files of the app from the cache', () => {
    expect(classifyRequest(request('/assets/index-abc123.js', { mode: 'no-cors' }), ORIGIN)).toBe('static')
    expect(classifyRequest(request('/icons/icon-192.png'), ORIGIN)).toBe('static')
    expect(classifyRequest(request('/manifest.webmanifest'), ORIGIN)).toBe('static')
  })

  it.each(['/api/me', '/api/library/3/progress', '/api/sanctum/csrf-cookie', '/docs', '/docs/api', '/up'])(
    'never touches %s, which belongs to the server',
    (path) => {
      expect(classifyRequest(request(path), ORIGIN)).toBe('ignore')
      expect(classifyRequest(request(path, { mode: 'navigate' }), ORIGIN)).toBe('ignore')
    },
  )

  it('is not fooled by a screen whose name only starts like a server path', () => {
    expect(classifyRequest(request('/apiary', { mode: 'navigate' }), ORIGIN)).toBe('navigation')
    expect(classifyRequest(request('/upload', { mode: 'navigate' }), ORIGIN)).toBe('navigation')
  })

  it.each(['POST', 'PATCH', 'PUT', 'DELETE'])('leaves %s requests alone', (method) => {
    expect(classifyRequest(request('/assets/index.js', { method }), ORIGIN)).toBe('ignore')
  })

  it('leaves other sites alone, such as the cover images of Open Library', () => {
    expect(classifyRequest({ method: 'GET', mode: 'no-cors', url: 'https://covers.openlibrary.org/b/id/1-M.jpg' }, ORIGIN)).toBe('ignore')
  })
})

describe('caches', () => {
  it('names the cache of a version', () => {
    expect(shellCacheName('84d3f2a3ad80')).toBe('bookshelf-shell-84d3f2a3ad80')
  })

  it('finds the caches of earlier versions and leaves everything else', () => {
    const names = ['bookshelf-shell-old1', 'bookshelf-shell-current', 'bookshelf-shell-old2', 'some-other-app', 'workbox-precache']

    expect(staleCaches(names, 'bookshelf-shell-current')).toEqual(['bookshelf-shell-old1', 'bookshelf-shell-old2'])
  })

  it('has nothing to remove at the very first install', () => {
    expect(staleCaches([], 'bookshelf-shell-current')).toEqual([])
  })
})
