import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/http'
import type { User } from '@/types/api'
import { useAuthStore } from './auth'

vi.mock('@/api/auth', () => ({
  authApi: { me: vi.fn(), login: vi.fn(), register: vi.fn(), logout: vi.fn() },
}))

const user: User = { id: 1, name: 'Olena', email: 'olena@example.com', timezone: 'Europe/Kyiv' }

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
})
