import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import RatingStars from './RatingStars.vue'

describe('RatingStars', () => {
  it('emits the tapped rating', async () => {
    const wrapper = mount(RatingStars, { props: { modelValue: null, label: 'Rating' } })

    await wrapper.findAll('button')[3]!.trigger('click')

    expect(wrapper.emitted('update:modelValue')).toEqual([[4]])
  })

  it('clears the rating when the current one is tapped again', async () => {
    const wrapper = mount(RatingStars, { props: { modelValue: 4, label: 'Rating' } })

    await wrapper.findAll('button')[3]!.trigger('click')

    expect(wrapper.emitted('update:modelValue')).toEqual([[null]])
  })

  it('marks the current rating as pressed', () => {
    const wrapper = mount(RatingStars, { props: { modelValue: 2, label: 'Rating' } })

    const pressed = wrapper.findAll('button').map((button) => button.attributes('aria-pressed'))

    expect(pressed).toEqual(['false', 'true', 'false', 'false', 'false'])
  })

  it('renders no buttons when read-only', () => {
    const wrapper = mount(RatingStars, { props: { modelValue: 3, label: 'Rating', readonly: true } })

    expect(wrapper.findAll('button')).toHaveLength(0)
  })
})
