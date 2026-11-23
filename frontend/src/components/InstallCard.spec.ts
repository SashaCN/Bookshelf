import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createTestEnvironment } from '@/test/utils'

function promptEvent() {
  const event = new Event('beforeinstallprompt', { cancelable: true }) as Event & { prompt: ReturnType<typeof vi.fn>; userChoice: Promise<unknown> }
  event.prompt = vi.fn().mockResolvedValue(undefined)
  event.userChoice = Promise.resolve({ outcome: 'accepted' })
  return event
}

async function mountCard() {
  vi.resetModules()
  const { listenForInstallPrompt } = await import('@/pwa/install')
  listenForInstallPrompt()
  const { default: InstallCard } = await import('./InstallCard.vue')
  const { plugins } = createTestEnvironment()

  return mount(InstallCard, { global: { plugins } })
}

describe('InstallCard', () => {
  beforeEach(() => {
    vi.stubGlobal('matchMedia', () => ({ matches: false }))
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('is not there when the browser cannot install the app', async () => {
    const wrapper = await mountCard()

    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('offers the install button once the browser allows it, and installs on tap', async () => {
    const wrapper = await mountCard()
    const event = promptEvent()

    window.dispatchEvent(event)
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Встановіть Bookshelf')

    await wrapper.get('button').trigger('click')

    expect(event.prompt).toHaveBeenCalledTimes(1)
  })

  it('explains how to do it by hand on an iPhone', async () => {
    vi.spyOn(navigator, 'userAgent', 'get').mockReturnValue('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari')

    const wrapper = await mountCard()

    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.text()).toContain('На початковий екран')
  })
})
