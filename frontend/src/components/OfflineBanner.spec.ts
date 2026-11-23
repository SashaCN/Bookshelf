import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { i18n } from '@/i18n'
import OfflineBanner from './OfflineBanner.vue'

afterEach(() => vi.restoreAllMocks())

describe('OfflineBanner', () => {
  it('shows nothing while online and a status message when offline', async () => {
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true)
    const wrapper = mount(OfflineBanner, { global: { plugins: [i18n] } })
    expect(wrapper.find('[role="status"]').text()).toBe('')

    window.dispatchEvent(new Event('offline'))
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false)
    window.dispatchEvent(new Event('offline'))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="status"]').text()).toBe('Ви офлайн. Показуємо останні збережені дані.')

    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true)
    window.dispatchEvent(new Event('online'))
    await wrapper.vm.$nextTick()
    expect(wrapper.find('[role="status"]').text()).toBe('')
    wrapper.unmount()
  })
})
