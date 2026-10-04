import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createTestEnvironment } from '@/test/utils'
import NotFoundView from './NotFoundView.vue'

describe('NotFoundView', () => {
  it('says there is nothing here and leads home', async () => {
    const { plugins, router } = createTestEnvironment()
    await router.push('/')

    const wrapper = mount(NotFoundView, { global: { plugins } })

    expect(wrapper.text()).toContain('Не знайдено')
    expect(wrapper.text()).toContain('Такої сторінки не існує')
    expect(wrapper.get('a:not([aria-label])').attributes('href')).toBe('/')
  })
})
