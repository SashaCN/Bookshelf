import { beforeEach, describe, expect, it, vi } from 'vitest'
import { authApi } from './auth'
import { http } from './http'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

describe('authApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('marks the followers list as seen', async () => {
    vi.mocked(http.post).mockResolvedValue(null)

    await authApi.followersSeen()

    expect(http.post).toHaveBeenCalledWith('/api/me/followers-seen')
  })
})
