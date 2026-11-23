import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { NetworkError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { createTestEnvironment } from '@/test/utils'
import App from './App.vue'

vi.mock('@/api/auth', () => ({ authApi: { me: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() } }))

async function mountApp() {
  const { plugins, router } = createTestEnvironment()
  await router.push('/')
  return { wrapper: mount(App, { global: { plugins } }), auth: useAuthStore() }
}

describe('App', () => {
  it('shows a splash, not a blank page, while the server is asked who is signed in', async () => {
    vi.mocked(authApi.me).mockReturnValue(new Promise(() => {}))
    const { wrapper, auth } = await mountApp()

    void auth.init()
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Bookshelf')
  })

  it('shows the screens once the question is answered', async () => {
    vi.mocked(authApi.me).mockResolvedValue({ id: 1, name: 'Olena', email: 'o@example.com', handle: 'olena', bio: null, timezone: 'Europe/Kyiv' })
    const { wrapper, auth } = await mountApp()

    await auth.init()
    await flushPromises()

    expect(wrapper.find('[aria-busy="true"]').exists()).toBe(false)
    expect(wrapper.find('main').exists()).toBe(false)
  })

  it('shows the offline screen when the server cannot be reached', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new NetworkError())
    const { wrapper, auth } = await mountApp()

    await auth.init()
    await flushPromises()

    expect(wrapper.text()).toContain('Немає з’єднання')
  })
})
