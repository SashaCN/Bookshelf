import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

class FakeWorker extends EventTarget {
  state: ServiceWorkerState = 'installing'
  postMessage = vi.fn()
}

class FakeRegistration extends EventTarget {
  installing: FakeWorker | null = null
  waiting: FakeWorker | null = null
}

class FakeContainer extends EventTarget {
  controller: object | null = {}
}

/** The module keeps its state between calls, so every test gets a fresh copy of it. */
async function freshModule() {
  vi.resetModules()
  return import('./updates')
}

function asRegistration(registration: FakeRegistration) {
  return registration as unknown as ServiceWorkerRegistration
}

function asContainer(container: FakeContainer) {
  return container as unknown as ServiceWorkerContainer
}

describe('watching for updates', () => {
  let reload: ReturnType<typeof vi.fn>

  beforeEach(() => {
    reload = vi.fn()
    vi.stubGlobal('location', { ...window.location, reload })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('announces a new version that finished installing while the app is open', async () => {
    const { updateReady, watchForUpdates } = await freshModule()
    const registration = new FakeRegistration()
    watchForUpdates(asRegistration(registration), asContainer(new FakeContainer()))
    expect(updateReady.value).toBe(false)

    const worker = new FakeWorker()
    registration.installing = worker
    registration.dispatchEvent(new Event('updatefound'))
    worker.state = 'installing'
    worker.dispatchEvent(new Event('statechange'))
    expect(updateReady.value).toBe(false)

    worker.state = 'installed'
    worker.dispatchEvent(new Event('statechange'))

    expect(updateReady.value).toBe(true)
  })

  it('announces a version that was already waiting when the app opened', async () => {
    const { updateReady, watchForUpdates } = await freshModule()
    const registration = new FakeRegistration()
    registration.waiting = new FakeWorker()

    watchForUpdates(asRegistration(registration), asContainer(new FakeContainer()))

    expect(updateReady.value).toBe(true)
  })

  it('says nothing about the very first install, which has no earlier version to replace', async () => {
    const { updateReady, watchForUpdates } = await freshModule()
    const registration = new FakeRegistration()
    const container = new FakeContainer()
    container.controller = null
    watchForUpdates(asRegistration(registration), asContainer(container))

    const worker = new FakeWorker()
    registration.installing = worker
    registration.dispatchEvent(new Event('updatefound'))
    worker.state = 'installed'
    worker.dispatchEvent(new Event('statechange'))

    expect(updateReady.value).toBe(false)
  })

  it('tells the waiting worker to take over when the reader agrees, and reloads once it has', async () => {
    const { applyUpdate, watchForUpdates } = await freshModule()
    const registration = new FakeRegistration()
    const container = new FakeContainer()
    registration.waiting = new FakeWorker()
    watchForUpdates(asRegistration(registration), asContainer(container))

    applyUpdate()
    expect(registration.waiting.postMessage).toHaveBeenCalledWith({ type: 'SKIP_WAITING' })
    expect(reload).not.toHaveBeenCalled()

    container.dispatchEvent(new Event('controllerchange'))
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('does not reload for a change of worker the reader did not ask for', async () => {
    const { watchForUpdates } = await freshModule()
    const container = new FakeContainer()
    watchForUpdates(asRegistration(new FakeRegistration()), asContainer(container))

    container.dispatchEvent(new Event('controllerchange'))

    expect(reload).not.toHaveBeenCalled()
  })

  it('does nothing when asked to apply an update that is not there', async () => {
    const { applyUpdate } = await freshModule()

    expect(() => applyUpdate()).not.toThrow()
  })
})
