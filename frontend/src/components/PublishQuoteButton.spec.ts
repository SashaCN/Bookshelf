import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createTestEnvironment } from '@/test/utils'
import PublishQuoteButton from './PublishQuoteButton.vue'

describe('PublishQuoteButton', () => {
  it('is a link to the screen that publishes this quote', () => {
    const { plugins } = createTestEnvironment()

    const wrapper = mount(PublishQuoteButton, { props: { quoteId: 12 }, global: { plugins } })

    expect(wrapper.text()).toBe('Опублікувати')
    expect(wrapper.get('a').attributes('href')).toBe('/community/new?quote=12')
  })
})
