import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { NetworkError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { createTestEnvironment } from '@/test/utils'
import OfflineGate from './OfflineGate.vue'

vi.mock('@/api/auth', () => ({ authApi: { me: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() } }))

async function mountGate() {
  const { plugins, router } = createTestEnvironment()
  router.addRoute({ path: '/needs-login', name: 'needs-login', component: { template: '<div />' }, meta: { requiresAuth: true } })
  await router.push({ name: 'needs-login' })
  const auth = useAuthStore()
  vi.mocked(authApi.me).mockRejectedValueOnce(new NetworkError())
  await auth.init()

  return { wrapper: mount(OfflineGate, { global: { plugins } }), auth, router }
}

describe('OfflineGate', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('says the server cannot be reached and offers to try again', async () => {
    const { wrapper } = await mountGate()

    expect(wrapper.text()).toContain('Немає з’єднання')
    expect(wrapper.get('button').text()).toBe('Повторити')
  })

  it('goes on once the server answers', async () => {
    const { wrapper, auth, router } = await mountGate()
    vi.mocked(authApi.me).mockResolvedValue({ id: 1, name: 'Olena', email: 'o@example.com', timezone: 'Europe/Kyiv' })

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(auth.unreachable).toBe(false)
    expect(auth.isAuthenticated).toBe(true)
    expect(router.currentRoute.value.name).toBe('needs-login')
  })

  it('sends the visitor to the sign-in form when the server answers that nobody is signed in', async () => {
    const { wrapper, auth, router } = await mountGate()
    const { ApiError } = await import('@/api/http')
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(auth.unreachable).toBe(false)
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('stays put when the server is still out of reach', async () => {
    const { wrapper, auth, router } = await mountGate()
    vi.mocked(authApi.me).mockRejectedValue(new NetworkError())

    await wrapper.get('button').trigger('click')
    await flushPromises()

    expect(auth.unreachable).toBe(true)
    expect(router.currentRoute.value.name).toBe('needs-login')
    expect(wrapper.get('button').attributes('disabled')).toBeUndefined()
  })
})
