import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createTestEnvironment } from '@/test/utils'
import SkeletonList from './SkeletonList.vue'

describe('SkeletonList', () => {
  it('draws placeholders and tells screen readers that something is loading', () => {
    const { plugins } = createTestEnvironment()
    const wrapper = mount(SkeletonList, { props: { count: 4 }, global: { plugins } })

    expect(wrapper.findAll('[aria-hidden="true"]')).toHaveLength(4)
    expect(wrapper.get('[role="status"]').attributes('aria-busy')).toBe('true')
    expect(wrapper.text()).toBe('Завантаження…')
  })

  it('makes taller placeholders for bigger blocks', () => {
    const { plugins } = createTestEnvironment()
    const wrapper = mount(SkeletonList, { props: { tall: true }, global: { plugins } })

    expect(wrapper.findAll('[aria-hidden="true"]')).toHaveLength(3)
    expect(wrapper.get('[aria-hidden="true"]').classes()).toContain('h-44')
  })
})
