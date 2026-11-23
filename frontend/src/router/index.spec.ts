import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { router } from './index'

vi.mock('@/api/auth', () => ({ authApi: { me: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() } }))

const user = { id: 1, name: 'Olena', email: 'olena@example.com', handle: 'olena', bio: null, timezone: 'Europe/Kyiv' }

describe('the app router', () => {
  beforeEach(async () => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
    // Every test starts outside of the app and with nobody signed in.
    await router.replace('/login').catch(() => undefined)
  })

  it('names the screen in the browser tab', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/stats')
    expect(document.title).toBe('Статистика · Bookshelf')

    await router.push('/quotes/new')
    expect(document.title).toBe('Нова цитата · Bookshelf')

    await router.push('/profile')
    expect(document.title).toBe('Профіль · Bookshelf')
  })

  it('shows a page of its own for an address that does not exist, inside the app', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/nothing/here')

    expect(router.currentRoute.value.name).toBe('not-found')
    expect(router.currentRoute.value.meta.tab).toBeUndefined()
    expect(document.title).toBe('Не знайдено · Bookshelf')
  })

  it('sends a guest who asks for an unknown address to the sign-in form', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))
    useAuthStore().user = null

    await router.push('/nothing/here')

    expect(router.currentRoute.value.name).toBe('login')
  })

  it('lets a guest open the password reset screens, with the token from the link', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))

    await router.push('/forgot-password')
    expect(router.currentRoute.value.name).toBe('forgot-password')
    expect(document.title).toBe('Відновлення пароля · Bookshelf')

    await router.push('/reset-password/tok123?email=a%40b.test')
    expect(router.currentRoute.value.name).toBe('reset-password')
    expect(router.currentRoute.value.params.token).toBe('tok123')
  })

  it('keeps a signed-in reader away from the password reset screens', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)

    await router.push('/forgot-password')

    expect(router.currentRoute.value.name).toBe('home')
  })
})
