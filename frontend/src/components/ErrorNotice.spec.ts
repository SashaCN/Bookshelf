import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { i18n } from '@/i18n'
import ErrorNotice from './ErrorNotice.vue'

describe('ErrorNotice', () => {
  afterEach(() => {
    Reflect.deleteProperty(Element.prototype, 'scrollIntoView')
  })

  it('scrolls itself into view when it appears and when its message changes', async () => {
    const scrollIntoView = vi.fn()
    Element.prototype.scrollIntoView = scrollIntoView

    const wrapper = mount(ErrorNotice, { props: { message: 'First' }, global: { plugins: [i18n] } })
    expect(scrollIntoView).toHaveBeenCalledTimes(1)

    await wrapper.setProps({ message: 'Second' })
    await nextTick()
    expect(scrollIntoView).toHaveBeenCalledTimes(2)
  })

  it('works where scrolling is not available and offers a retry only on request', async () => {
    const wrapper = mount(ErrorNotice, { props: { message: 'Oops', retryable: true }, global: { plugins: [i18n] } })

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('retry')).toHaveLength(1)
    expect(wrapper.get('[role="alert"]').text()).toContain('Oops')
  })
})
