import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from './auth'
import { http } from './http'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn(), put: vi.fn() },
}))

describe('authApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('asks for a reset link and resets the password through Fortify', async () => {
    vi.mocked(http.post).mockResolvedValue({})
    const payload = { token: 'abc', email: 'a@b.test', password: 'new-password', password_confirmation: 'new-password' }

    await authApi.forgotPassword('a@b.test')
    await authApi.resetPassword(payload)

    expect(http.post).toHaveBeenNthCalledWith(1, '/api/auth/forgot-password', { email: 'a@b.test' })
    expect(http.post).toHaveBeenNthCalledWith(2, '/api/auth/reset-password', payload)
  })

  it('changes the password through Fortify', async () => {
    vi.mocked(http.put).mockResolvedValue({})
    const payload = { current_password: 'old-password', password: 'new-password', password_confirmation: 'new-password' }

    await authApi.changePassword(payload)

    expect(http.put).toHaveBeenCalledWith('/api/auth/user/password', payload)
  })

  it('deletes the account with the password in the body', async () => {
    vi.mocked(http.delete).mockResolvedValue(null)

    await authApi.deleteMe('secret-password')

    expect(http.delete).toHaveBeenCalledWith('/api/me', { body: { password: 'secret-password' } })
  })
})
