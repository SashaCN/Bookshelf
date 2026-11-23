import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { createTestRouter } from '@/test/utils'
import { createPinia } from 'pinia'
import { i18n } from '@/i18n'
import AppLayout from './AppLayout.vue'

// Plain component definitions: these are test doubles, not components of the app.
const Broken = {
  setup() {
    throw new Error('A screen that cannot be drawn')
  },
  template: '<div />',
}

const Fine = { template: '<p>All is well</p>' }

async function mountLayout() {
  const router = createTestRouter()
  router.addRoute({ path: '/broken', name: 'broken', component: Broken })
  router.addRoute({ path: '/fine', name: 'fine', component: Fine })
  await router.push('/fine')

  const wrapper = mount(AppLayout, { global: { plugins: [createPinia(), router, i18n] } })
  await flushPromises()

  return { wrapper, router }
}

describe('AppLayout', () => {
  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('shows the screen and the tab bar', async () => {
    const { wrapper } = await mountLayout()

    expect(wrapper.text()).toContain('All is well')
    expect(wrapper.find('nav').exists()).toBe(true)
  })

  it('shows a way out instead of a blank page when a screen fails, and recovers on the next screen', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => undefined)
    const { wrapper, router } = await mountLayout()

    await router.push('/broken')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Щось пішло не так')
    expect(wrapper.find('nav').exists()).toBe(true)

    await router.push('/fine')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('All is well')
  })

  it('moves focus to the heading of the new screen, but not when only the query string changes', async () => {
    const { wrapper, router } = await mountLayout()
    const Heading = { template: '<h1 tabindex="-1">Screen</h1>' }
    router.addRoute({ path: '/heading', name: 'heading', component: Heading })
    const host = document.createElement('div')
    document.body.append(host)
    const attached = mount(AppLayout, { attachTo: host, global: { plugins: [createPinia(), router, i18n] } })

    await router.push('/heading')
    await flushPromises()
    expect(document.activeElement?.tagName).toBe('H1')

    const input = document.createElement('input')
    document.body.append(input)
    input.focus()
    await router.replace({ path: '/heading', query: { status: 'reading' } })
    await flushPromises()
    expect(document.activeElement).toBe(input)

    input.remove()
    attached.unmount()
    wrapper.unmount()
    host.remove()
  })
})
