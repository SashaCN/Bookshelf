import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeUserBook } from '@/test/fixtures'
import { http } from './http'
import { libraryApi } from './library'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

describe('libraryApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('starts a reread with a body-less POST and unwraps the entry', async () => {
    const entry = makeUserBook({ id: 4, status: 'reading', times_read: 1 })
    vi.mocked(http.post).mockResolvedValue({ data: entry })

    await expect(libraryApi.reread(4)).resolves.toEqual(entry)

    expect(http.post).toHaveBeenCalledWith('/api/library/4/reread')
  })
})
