import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { i18n } from '@/i18n'
import AppLayout from './AppLayout.vue'
import PageHeader from './PageHeader.vue'

const page = (title: string) => ({ components: { PageHeader }, template: `<PageHeader title="${title}" />` })

async function setUp() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      {
        path: '/',
        component: AppLayout,
        children: [
          { path: '', name: 'home', component: page('Home') },
          { path: 'library', name: 'library', component: page('Library') },
          { path: 'quotes', name: 'quotes', component: page('Quotes') },
          { path: 'stats', name: 'stats', component: page('Stats') },
          { path: 'profile', name: 'profile', component: page('Profile') },
        ],
      },
    ],
  })
  await router.push('/')
  const wrapper = mount({ template: '<RouterView />' }, { attachTo: document.body, global: { plugins: [pinia, router, i18n] } })
  await flushPromises()
  return { wrapper, router }
}

describe('AppLayout', () => {
  it('moves focus to the heading of the new screen', async () => {
    const { wrapper, router } = await setUp()

    await router.push('/stats')
    await flushPromises()

    expect(document.activeElement?.tagName).toBe('H1')
    expect(document.activeElement?.textContent).toBe('Stats')
    wrapper.unmount()
  })

  it('leaves focus alone when only the query string changes', async () => {
    const { wrapper, router } = await setUp()
    await router.push('/library')
    await flushPromises()
    const input = document.createElement('input')
    document.body.append(input)
    input.focus()

    await router.replace({ path: '/library', query: { status: 'reading' } })
    await flushPromises()

    expect(document.activeElement).toBe(input)
    input.remove()
    wrapper.unmount()
  })
})
