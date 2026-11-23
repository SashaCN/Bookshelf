import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { updateReady } from '@/pwa/updates'
import { createTestEnvironment } from '@/test/utils'
import AppBanners from './AppBanners.vue'

const applyUpdate = vi.hoisted(() => vi.fn())

vi.mock('@/pwa/updates', async (importOriginal) => ({ ...(await importOriginal<typeof import('@/pwa/updates')>()), applyUpdate }))

function mountBanners() {
  const { plugins } = createTestEnvironment()
  return mount(AppBanners, { global: { plugins } })
}

describe('AppBanners', () => {
  afterEach(() => {
    window.dispatchEvent(new Event('online'))
    updateReady.value = false
    applyUpdate.mockReset()
  })

  it('takes no room while all is well', () => {
    expect(mountBanners().html()).toBe('<!--v-if-->')
  })

  it('warns that changes are not saved while offline, and goes away when the connection returns', async () => {
    const wrapper = mountBanners()

    window.dispatchEvent(new Event('offline'))
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('Немає інтернету')

    window.dispatchEvent(new Event('online'))
    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toBe('')
  })

  it('offers a new version and switches to it on request', async () => {
    updateReady.value = true
    const wrapper = mountBanners()

    expect(wrapper.text()).toContain('Доступна нова версія')

    await wrapper.get('button').trigger('click')

    expect(applyUpdate).toHaveBeenCalledTimes(1)
  })

  it('can show both at once', async () => {
    updateReady.value = true
    const wrapper = mountBanners()

    window.dispatchEvent(new Event('offline'))
    await wrapper.vm.$nextTick()

    expect(wrapper.findAll('[role="status"]')).toHaveLength(2)
  })
})
