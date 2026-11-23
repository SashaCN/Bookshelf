import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { authApi } from '@/api/auth'
import { router } from './index'

vi.mock('@/api/auth', () => ({ authApi: { me: vi.fn() } }))

const user = { id: 1, name: 'Olena', email: 'olena@example.com', timezone: 'Europe/Kyiv', username: null, is_public: false, new_followers_count: 0 }

describe('router guards', () => {
  beforeEach(async () => {
    vi.resetAllMocks()
    // Leave the previous test's page as a guest, then start the test with a store that has not asked the API yet.
    setActivePinia(createPinia())
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))
    await router.replace('/login')
    setActivePinia(createPinia())
  })

  it('sends a guest from a private page to the login form', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))

    await router.push('/library')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('remembers the page a guest wanted and returns there after signing in', async () => {
    await router.push('/library/7')

    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/library/7')

    // The login form pushes the remembered page; for a signed-in reader the guest-only route honours it too.
    setActivePinia(createPinia())
    vi.mocked(authApi.me).mockResolvedValue(user)
    await router.push({ name: 'login', query: { redirect: '/stats' } })

    expect(router.currentRoute.value.fullPath).toBe('/stats')
  })

  it('does not add a redirect for the home page and ignores a foreign one', async () => {
    await router.push('/')
    expect(router.currentRoute.value.query.redirect).toBeUndefined()

    setActivePinia(createPinia())
    vi.mocked(authApi.me).mockResolvedValue(user)
    await router.push({ name: 'login', query: { redirect: 'https://evil.example' } })

    expect(router.currentRoute.value.name).toBe('home')
  })

  it('lets a signed-in reader open a private page', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/stats')

    expect(router.currentRoute.value.name).toBe('stats')
  })

  it('sends a signed-in reader away from the guest-only pages', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/register')

    expect(router.currentRoute.value.name).toBe('home')
  })

  it('opens the social pages for a signed-in reader and guards them from guests', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)
    await router.push('/u/olena/followers')
    expect(router.currentRoute.value.name).toBe('followers')

    await router.push('/feed')
    expect(router.currentRoute.value.name).toBe('feed')

    setActivePinia(createPinia())
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))
    await router.push('/u/olena/followers')
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('lets a guest open a shared public profile without signing in', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))

    await router.push('/u/olena')

    expect(router.currentRoute.value.name).toBe('public-profile')
    expect(router.currentRoute.value.params.username).toBe('olena')
  })

  it('keeps a signed-in reader on a public profile', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/u/olena')

    expect(router.currentRoute.value.name).toBe('public-profile')
  })

  it('treats the visitor as a guest when the API is unreachable', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new Error('Network down'))

    await router.push('/quotes')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('redirects an unknown address to the home page', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/no/such/page')

    expect(router.currentRoute.value.name).toBe('home')
  })
})
