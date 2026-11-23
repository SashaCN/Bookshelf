import { afterEach, describe, expect, it, vi } from 'vitest'
import swSource from '../../public/sw.js?raw'
import { API_CACHE_NAME, clearApiCache } from './clearApiCache'

afterEach(() => {
  vi.unstubAllGlobals()
  Reflect.deleteProperty(navigator, 'serviceWorker')
})

describe('clearApiCache', () => {
  it('tells the worker and deletes the cache directly', () => {
    const postMessage = vi.fn()
    const remove = vi.fn().mockResolvedValue(true)
    Object.defineProperty(navigator, 'serviceWorker', { value: { controller: { postMessage } }, configurable: true })
    vi.stubGlobal('caches', { delete: remove })

    clearApiCache()

    expect(postMessage).toHaveBeenCalledWith({ type: 'clear-api-cache' })
    expect(remove).toHaveBeenCalledWith(API_CACHE_NAME)
  })

  it('survives a missing Cache API and a throwing worker', () => {
    Object.defineProperty(navigator, 'serviceWorker', {
      value: { controller: { postMessage: () => { throw new Error('x') } } },
      configurable: true,
    })
    vi.stubGlobal('caches', { delete: () => { throw new Error('y') } })
    expect(() => clearApiCache()).not.toThrow()
  })

  it('uses the cache name the worker uses', () => {
    expect(swSource).toContain(`\`bookshelf-api-\${VERSION}\``)
    expect(API_CACHE_NAME).toBe('bookshelf-api-v1')
  })
})
