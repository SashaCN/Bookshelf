import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createTestEnvironment } from '@/test/utils'
import TabBar from './TabBar.vue'

async function mountBar(name: string) {
  const { plugins, router } = createTestEnvironment()
  await router.push({ name })
  return mount(TabBar, { global: { plugins } })
}

describe('TabBar', () => {
  it('has a tab for reading, the library and the quotes', async () => {
    const wrapper = await mountBar('home')

    const tabs = wrapper.findAll('a').map((link) => [link.text(), link.attributes('href')])

    expect(tabs).toEqual([
      ['Читаю', '/'],
      ['Бібліотека', '/library'],
      ['Цитати', '/quotes'],
    ])
  })

  it('lays the tabs out in as many columns as there are tabs', async () => {
    const wrapper = await mountBar('home')

    expect(wrapper.get('ul').classes()).toEqual(expect.arrayContaining(['grid', 'grid-flow-col', 'auto-cols-fr']))
  })

  it.each([
    ['quotes', {}, 'Цитати'],
    ['quote-new', {}, 'Цитати'],
    ['quote-edit', { id: 1 }, 'Цитати'],
    ['quote-share', { id: 1 }, 'Цитати'],
    ['book', { id: 1 }, 'Бібліотека'],
    ['home', {}, 'Читаю'],
  ])('highlights the right tab on the "%s" page', async (name, params, label) => {
    const { plugins, router } = createTestEnvironment()
    await router.push({ name, params })
    const wrapper = mount(TabBar, { global: { plugins } })

    const current = wrapper.findAll('a').filter((link) => link.attributes('aria-current') === 'page')

    expect(current.map((link) => link.text())).toEqual([label])
  })
})
