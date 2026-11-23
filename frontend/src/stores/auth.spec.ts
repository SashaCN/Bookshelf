import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { makeQuote } from '@/test/fixtures'
import type { User } from '@/types/api'
import { useAuthStore } from './auth'
import { useQuotesStore } from './quotes'

vi.mock('@/api/auth', () => ({
  authApi: { me: vi.fn(), followersSeen: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() },
}))

const user: User = { id: 1, name: 'Olena', email: 'olena@example.com', timezone: 'Europe/Kyiv', username: null, is_public: false, new_followers_count: 0 }

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('treats a 401 on init as a guest', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(401, null))
    const auth = useAuthStore()

    await auth.init()

    expect(auth.isAuthenticated).toBe(false)
    expect(auth.initialized).toBe(true)
  })

  it('restores the session on init', async () => {
    vi.mocked(authApi.me).mockResolvedValue(user)
    const auth = useAuthStore()

    await auth.init()

    expect(auth.user).toEqual(user)
  })

  it('does not swallow unexpected errors on init', async () => {
    vi.mocked(authApi.me).mockRejectedValue(new ApiError(500, null))
    const auth = useAuthStore()

    await expect(auth.init()).rejects.toBeInstanceOf(ApiError)
    expect(auth.initialized).toBe(false)
  })

  it('loads the user after logging in and clears it on logout', async () => {
    vi.mocked(authApi.login).mockResolvedValue({})
    vi.mocked(authApi.me).mockResolvedValue(user)
    vi.mocked(authApi.logout).mockResolvedValue(null)
    const auth = useAuthStore()

    await auth.login({ email: user.email, password: 'secret-password' })
    expect(auth.user).toEqual(user)

    await auth.logout()
    expect(auth.user).toBeNull()
  })

  it('does not leave the previous reader\'s quotes behind', async () => {
    vi.mocked(authApi.login).mockResolvedValue({})
    vi.mocked(authApi.register).mockResolvedValue({})
    vi.mocked(authApi.me).mockResolvedValue(user)
    vi.mocked(authApi.logout).mockResolvedValue(null)
    const auth = useAuthStore()
    const quotes = useQuotesStore()

    quotes.daily = makeQuote()
    await auth.login({ email: user.email, password: 'secret-password' })
    expect(quotes.daily).toBeNull()

    quotes.daily = makeQuote()
    await auth.register({ name: user.name, email: user.email, password: 'secret-password', timezone: user.timezone })
    expect(quotes.daily).toBeNull()

    quotes.daily = makeQuote()
    await auth.logout()
    expect(quotes.daily).toBeNull()
  })

  it('drops the new-follower count once the list is marked as seen', async () => {
    vi.mocked(authApi.followersSeen).mockResolvedValue(null)
    const auth = useAuthStore()
    auth.user = { ...user, new_followers_count: 3 }

    await auth.markFollowersSeen()

    expect(authApi.followersSeen).toHaveBeenCalledOnce()
    expect(auth.user?.new_followers_count).toBe(0)
  })

  it('does not call the server when there is nothing new', async () => {
    const auth = useAuthStore()
    auth.user = { ...user }

    await auth.markFollowersSeen()

    expect(authApi.followersSeen).not.toHaveBeenCalled()
  })

  it('keeps the count when the server refuses', async () => {
    vi.mocked(authApi.followersSeen).mockRejectedValue(new ApiError(500, null))
    const auth = useAuthStore()
    auth.user = { ...user, new_followers_count: 3 }

    await expect(auth.markFollowersSeen()).rejects.toBeInstanceOf(ApiError)
    expect(auth.user?.new_followers_count).toBe(3)
  })
})
