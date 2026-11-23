import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeUserBook } from '@/test/fixtures'
import { http } from './http'
import { catalogApi, libraryApi } from './library'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

describe('libraryApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('gives the catalog search 4 seconds, a second more than the server gives Open Library', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [] })

    await catalogApi.search('Тигролови')

    expect(http.get).toHaveBeenCalledWith('/api/catalog/search?q=%D0%A2%D0%B8%D0%B3%D1%80%D0%BE%D0%BB%D0%BE%D0%B2%D0%B8', { timeoutMs: 4000 })
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
