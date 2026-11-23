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

  it('uploads the Goodreads file as multipart form data under "file" and unwraps the summary', async () => {
    const summary = { imported: 2, skipped_existing: 1, skipped_invalid: 0, errors: [] }
    vi.mocked(http.post).mockResolvedValue({ data: summary })
    const file = new File(['Title,Author'], 'export.csv', { type: 'text/csv' })

    await expect(libraryApi.importGoodreads(file)).resolves.toEqual(summary)

    const [path, body] = vi.mocked(http.post).mock.calls[0]
    expect(path).toBe('/api/library/import')
    expect(body).toBeInstanceOf(FormData)
    expect((body as FormData).get('file')).toBe(file)
  })
})
