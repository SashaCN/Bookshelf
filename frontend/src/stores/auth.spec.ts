import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import { makeQuote } from '@/test/fixtures'
import type { User } from '@/types/api'
import { useAuthStore } from './auth'
import { useQuotesStore } from './quotes'

vi.mock('@/api/auth', () => ({
  authApi: { me: vi.fn(), followersSeen: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn(), changePassword: vi.fn(), deleteMe: vi.fn() },
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

  it('passes a password change to the API and keeps the session', async () => {
    vi.mocked(authApi.changePassword).mockResolvedValue({})
    const auth = useAuthStore()
    auth.user = user
    const payload = { current_password: 'old-password', password: 'new-password', password_confirmation: 'new-password' }

    await auth.changePassword(payload)

    expect(authApi.changePassword).toHaveBeenCalledWith(payload)
    expect(auth.user).toEqual(user)
  })

  it('lets a failed password change through to the caller', async () => {
    vi.mocked(authApi.changePassword).mockRejectedValue(new ApiError(422, { errors: { current_password: ['x'] } }))

    await expect(useAuthStore().changePassword({ current_password: 'a', password: 'b', password_confirmation: 'b' })).rejects.toMatchObject({ status: 422 })
  })

  it('forgets the reader and everything they had after the account is deleted', async () => {
    vi.mocked(authApi.deleteMe).mockResolvedValue(null)
    const auth = useAuthStore()
    auth.user = user
    const quotes = useQuotesStore()
    quotes.items = [makeQuote({ id: 1 })]

    await auth.deleteAccount('secret-password')

    expect(authApi.deleteMe).toHaveBeenCalledWith('secret-password')
    expect(auth.isAuthenticated).toBe(false)
    expect(quotes.items).toEqual([])
  })

  it('stays signed in when the account cannot be deleted', async () => {
    vi.mocked(authApi.deleteMe).mockRejectedValue(new ApiError(422, { errors: { password: ['x'] } }))
    const auth = useAuthStore()
    auth.user = user

    await expect(auth.deleteAccount('wrong')).rejects.toBeInstanceOf(ApiError)

    expect(auth.user).toEqual(user)
  })
})
