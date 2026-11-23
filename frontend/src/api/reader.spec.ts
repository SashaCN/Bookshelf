import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeReaderChapter, makeReaderDoc } from '@/test/readerFixtures'
import { http } from './http'
import { readerApi } from './reader'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

describe('readerApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists and unwraps documents', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [makeReaderDoc({ id: 5 })] })

    expect((await readerApi.list())[0].id).toBe(5)
    expect(http.get).toHaveBeenCalledWith('/api/reader')
  })

  it('uploads the file as multipart form data under "file"', async () => {
    vi.mocked(http.post).mockResolvedValue({ data: makeReaderDoc({ id: 9 }) })
    const file = new File(['x'], 'book.epub', { type: 'application/epub+zip' })

    expect((await readerApi.upload(file)).id).toBe(9)

    const [path, body] = vi.mocked(http.post).mock.calls[0]
    expect(path).toBe('/api/reader')
    expect(body).toBeInstanceOf(FormData)
    expect((body as FormData).get('file')).toBeInstanceOf(File)
  })

  it('gets, removes, saves the position and loads a chapter', async () => {
    vi.mocked(http.get).mockResolvedValueOnce({ data: makeReaderDoc() }).mockResolvedValueOnce({ data: makeReaderChapter({ index: 2 }) })
    vi.mocked(http.put).mockResolvedValue({ data: makeReaderDoc() })
    vi.mocked(http.delete).mockResolvedValue(null)

    vi.mocked(http.patch).mockResolvedValue({ data: makeReaderDoc({ user_book_id: 7 }) })

    await readerApi.get(3)
    await readerApi.remove(3)
    await readerApi.savePosition(3, { chapter: 2, fraction: 0 })
    expect((await readerApi.chapter(3, 2)).index).toBe(2)
    expect((await readerApi.link(3, 7)).user_book_id).toBe(7)
    await readerApi.link(3, null)

    expect(http.get).toHaveBeenNthCalledWith(1, '/api/reader/3')
    expect(http.delete).toHaveBeenCalledWith('/api/reader/3')
    expect(http.put).toHaveBeenCalledWith('/api/reader/3/position', { chapter: 2, fraction: 0 })
    expect(http.patch).toHaveBeenNthCalledWith(1, '/api/reader/3', { user_book_id: 7 })
    expect(http.patch).toHaveBeenNthCalledWith(2, '/api/reader/3', { user_book_id: null })
    expect(http.get).toHaveBeenNthCalledWith(2, '/api/reader/3/chapters/2')
  })
})
