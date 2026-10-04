import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function promptEvent(outcome: 'accepted' | 'dismissed' = 'accepted') {
  const event = new Event('beforeinstallprompt', { cancelable: true }) as Event & {
    prompt: ReturnType<typeof vi.fn>
    userChoice: Promise<{ outcome: string }>
  }
  event.prompt = vi.fn().mockResolvedValue(undefined)
  event.userChoice = Promise.resolve({ outcome })
  return event
}

/** The module keeps its state and its listeners between calls, so every test gets a fresh copy of it. */
async function freshModule() {
  vi.resetModules()
  const module = await import('./install')
  module.listenForInstallPrompt()
  return module
}

describe('installing the app', () => {
  beforeEach(() => {
    vi.stubGlobal('matchMedia', () => ({ matches: false }))
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('holds the browser\'s offer back until the reader asks for it', async () => {
    const { useInstall } = await freshModule()
    const install = useInstall()
    expect(install.canPrompt.value).toBe(false)

    const event = promptEvent()
    window.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
    expect(install.canPrompt.value).toBe(true)
    expect(event.prompt).not.toHaveBeenCalled()
  })

  it('shows the offer when asked, and uses it only once', async () => {
    const { useInstall } = await freshModule()
    const install = useInstall()
    const event = promptEvent()
    window.dispatchEvent(event)

    await install.install()

    expect(event.prompt).toHaveBeenCalledTimes(1)
    expect(install.canPrompt.value).toBe(false)

    await install.install()
    expect(event.prompt).toHaveBeenCalledTimes(1)
  })

  it('stops offering once the app is installed', async () => {
    const { useInstall } = await freshModule()
    const install = useInstall()
    window.dispatchEvent(promptEvent())

    window.dispatchEvent(new Event('appinstalled'))

    expect(install.canPrompt.value).toBe(false)
    expect(install.installed.value).toBe(true)
  })

  it('does not offer to install an app that is already running installed', async () => {
    vi.stubGlobal('matchMedia', (query: string) => ({ matches: query === '(display-mode: standalone)' }))
    const { useInstall } = await freshModule()
    const install = useInstall()

    window.dispatchEvent(promptEvent())

    expect(install.standalone).toBe(true)
    expect(install.canPrompt.value).toBe(false)
    expect(install.showIosHint.value).toBe(false)
  })

  describe('on an iPhone, where the browser cannot be asked', () => {
    beforeEach(() => {
      vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari')
    })

    afterEach(() => {
      vi.restoreAllMocks()
    })

    it('explains how to add the app by hand', async () => {
      const { useInstall } = await freshModule()
      const install = useInstall()

      expect(install.canPrompt.value).toBe(false)
      expect(install.showIosHint.value).toBe(true)
    })

    it('does not explain it to somebody who has already done it', async () => {
      vi.stubGlobal('matchMedia', () => ({ matches: true }))
      const { useInstall } = await freshModule()

      expect(useInstall().showIosHint.value).toBe(false)
    })
  })

  it('does not show the iPhone hint to other browsers', async () => {
    const { useInstall } = await freshModule()

    expect(useInstall().showIosHint.value).toBe(false)
  })
})
