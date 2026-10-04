import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function fakeWorkerContainer(overrides: Record<string, unknown> = {}) {
  const registration = Object.assign(new EventTarget(), {
    waiting: null,
    installing: null,
    update: vi.fn().mockResolvedValue(undefined),
    unregister: vi.fn().mockResolvedValue(true),
  })
  const container = Object.assign(new EventTarget(), {
    controller: null,
    register: vi.fn().mockResolvedValue(registration),
    getRegistrations: vi.fn().mockResolvedValue([registration]),
    ...overrides,
  })

  Object.defineProperty(navigator, 'serviceWorker', { value: container, configurable: true })
  return { container, registration }
}

/**
 * Runs the "load" handlers that registerServiceWorker() added. A real event would also wake the handlers that
 * earlier tests left on window.
 */
function pageLoaded(addListener: { mock: { calls: unknown[][] } }): void {
  for (const [type, handler] of addListener.mock.calls) {
    if (type === 'load') (handler as () => void)()
  }
}

/** The module keeps its state between calls, so every test gets a fresh copy of it. */
async function freshModule() {
  vi.resetModules()
  return import('./updates')
}

describe('registering the service worker', () => {
  beforeEach(() => {
    vi.stubGlobal('caches', {
      keys: vi.fn().mockResolvedValue(['bookshelf-shell-v1', 'another-app']),
      delete: vi.fn().mockResolvedValue(true),
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    vi.unstubAllEnvs()
    vi.unstubAllGlobals()
    Reflect.deleteProperty(navigator, 'serviceWorker')
  })

  describe('in a production build', () => {
    beforeEach(() => {
      vi.stubEnv('PROD', true)
    })

    it('registers /sw.js once the page has loaded, not before', async () => {
      const { container } = fakeWorkerContainer()
      const addListener = vi.spyOn(window, 'addEventListener')
      const { registerServiceWorker } = await freshModule()

      registerServiceWorker()
      expect(container.register).not.toHaveBeenCalled()

      pageLoaded(addListener)
      await vi.waitFor(() => expect(container.register).toHaveBeenCalledWith('/sw.js'))
    })

    it('looks for a new version when the app comes back to the screen', async () => {
      const { container, registration } = fakeWorkerContainer()
      const addListener = vi.spyOn(window, 'addEventListener')
      const { registerServiceWorker } = await freshModule()
      registerServiceWorker()
      pageLoaded(addListener)
      await vi.waitFor(() => expect(container.register).toHaveBeenCalled())
      // Registering is followed by the listener for the app returning to the screen.
      await Promise.resolve()

      vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
      document.dispatchEvent(new Event('visibilitychange'))

      expect(registration.update).toHaveBeenCalled()
    })

    it('lives on when the worker cannot be registered, and says why in the console', async () => {
      const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined)
      fakeWorkerContainer({ register: vi.fn().mockRejectedValue(new Error('refused')) })
      const addListener = vi.spyOn(window, 'addEventListener')
      const { registerServiceWorker } = await freshModule()

      registerServiceWorker()
      pageLoaded(addListener)

      await vi.waitFor(() => expect(warn).toHaveBeenCalled())
    })

    it('does nothing in a browser without service workers', async () => {
      const { registerServiceWorker } = await freshModule()

      expect(() => registerServiceWorker()).not.toThrow()
    })
  })

  describe('in development', () => {
    beforeEach(() => {
      vi.stubEnv('PROD', false)
    })

    it('registers nothing, and removes a worker and a cache that a production build left behind', async () => {
      const { container, registration } = fakeWorkerContainer()
      const addListener = vi.spyOn(window, 'addEventListener')
      const { registerServiceWorker } = await freshModule()

      registerServiceWorker()
      pageLoaded(addListener)

      await vi.waitFor(() => expect(registration.unregister).toHaveBeenCalled())
      await vi.waitFor(() => expect(caches.delete).toHaveBeenCalledWith('bookshelf-shell-v1'))
      expect(caches.delete).not.toHaveBeenCalledWith('another-app')
      expect(container.register).not.toHaveBeenCalled()
    })

    it('does not mind when the browser refuses to clean up', async () => {
      fakeWorkerContainer({ getRegistrations: vi.fn().mockRejectedValue(new Error('no')) })
      const { registerServiceWorker } = await freshModule()

      expect(() => registerServiceWorker()).not.toThrow()
      await Promise.resolve()
    })
  })
})
