import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeBookText, makeChapter } from '@/test/readerFixtures'
import { http } from './http'
import { readerApi } from './reader'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn(), upload: vi.fn() },
}))

describe('readerApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('uploads a book as a form with the file in the field "file", and unwraps the answer', async () => {
    vi.mocked(http.upload).mockResolvedValue({ data: makeBookText({ id: 5 }) })
    const file = new File(['epub'], 'book.epub', { type: 'application/epub+zip' })

    const text = await readerApi.upload(3, file)

    expect(text.id).toBe(5)
    const [path, form] = vi.mocked(http.upload).mock.calls[0]!
    expect(path).toBe('/api/library/3/text')
    expect(form).toBeInstanceOf(FormData)
    expect((form as FormData).get('file')).toBe(file)
    expect(vi.mocked(http.upload).mock.calls[0]![2]).toBeUndefined()
  })

  it('lets the caller choose how long to wait for an upload', async () => {
    vi.mocked(http.upload).mockResolvedValue({ data: makeBookText() })

    await readerApi.upload(3, new File(['epub'], 'book.epub'), 300_000)

    expect(vi.mocked(http.upload).mock.calls[0]![2]).toBe(300_000)
  })

  it('reads and removes the text of a library entry', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: makeBookText({ id: 8 }) })
    vi.mocked(http.delete).mockResolvedValue(null)

    expect((await readerApi.text(3)).id).toBe(8)
    await readerApi.removeText(3)

    expect(http.get).toHaveBeenCalledWith('/api/library/3/text')
    expect(http.delete).toHaveBeenCalledWith('/api/library/3/text')
  })

  it('reads a chapter and unwraps it', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: makeChapter({ position: 4, title: 'Four' }) })

    const chapter = await readerApi.chapter(8, 4)

    expect(http.get).toHaveBeenCalledWith('/api/book-texts/8/chapters/4')
    expect(chapter.title).toBe('Four')
  })

  it('saves the position and gives the answer as it is, with the library entry in it', async () => {
    const answer = { data: makeBookText(), meta: { user_book: {} } }
    vi.mocked(http.put).mockResolvedValue(answer)

    const result = await readerApi.savePosition(8, { chapter: 2, chapter_progress: 0.5 })

    expect(http.put).toHaveBeenCalledWith('/api/book-texts/8/position', { chapter: 2, chapter_progress: 0.5 })
    expect(result).toBe(answer)
  })

  it('saves the position with keepalive when the page is on its way out', async () => {
    vi.mocked(http.put).mockResolvedValue({ data: makeBookText(), meta: { user_book: {} } })

    await readerApi.savePosition(8, { chapter: 2, chapter_progress: 0.5 }, { keepalive: true })

    expect(http.put).toHaveBeenCalledWith('/api/book-texts/8/position', { chapter: 2, chapter_progress: 0.5 }, { keepalive: true })
  })
})
