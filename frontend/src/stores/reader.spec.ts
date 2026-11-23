import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { readerApi } from '@/api/reader'
import { makeReaderDoc } from '@/test/readerFixtures'
import { useReaderStore } from './reader'

vi.mock('@/api/reader', () => ({
  readerApi: { list: vi.fn(), get: vi.fn(), upload: vi.fn(), remove: vi.fn(), savePosition: vi.fn(), link: vi.fn(), chapter: vi.fn() },
}))

describe('reader store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('loads the list', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 2 }), makeReaderDoc({ id: 1 })])
    const reader = useReaderStore()

    await reader.load()

    expect(reader.loaded).toBe(true)
    expect(reader.items.map((item) => item.id)).toEqual([2, 1])
  })

  it('replaces a document with its re-linked copy', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1 })])
    vi.mocked(readerApi.link).mockResolvedValue(makeReaderDoc({ id: 1, user_book_id: 5, user_book: { id: 5, title: 'Альфа' } }))
    const reader = useReaderStore()
    await reader.load()

    await reader.link(1, 5)

    expect(readerApi.link).toHaveBeenCalledWith(1, 5)
    expect(reader.items[0].user_book?.title).toBe('Альфа')
  })

  it('puts an uploaded book first', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1 })])
    vi.mocked(readerApi.upload).mockResolvedValue(makeReaderDoc({ id: 3 }))
    const reader = useReaderStore()
    await reader.load()

    await reader.upload(new File(['x'], 'a.epub'))

    expect(reader.items.map((item) => item.id)).toEqual([3, 1])
  })

  it('removes a book', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1 }), makeReaderDoc({ id: 2 })])
    vi.mocked(readerApi.remove).mockResolvedValue(null)
    const reader = useReaderStore()
    await reader.load()

    await reader.remove(1)

    expect(reader.items.map((item) => item.id)).toEqual([2])
  })

  it('keeps the list when a delete fails', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1 })])
    vi.mocked(readerApi.remove).mockRejectedValue(new Error('x'))
    const reader = useReaderStore()
    await reader.load()

    await expect(reader.remove(1)).rejects.toThrow()

    expect(reader.items).toHaveLength(1)
  })

  it('stores the server copy after saving the position', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1, progress: 0 })])
    vi.mocked(readerApi.savePosition).mockResolvedValue(makeReaderDoc({ id: 1, progress: 50 }))
    const reader = useReaderStore()
    await reader.load()

    await reader.savePosition(1, { chapter: 6, fraction: 0 })

    expect(reader.items[0].progress).toBe(50)
  })

  it('uses a loaded book instead of asking again, and forgets all on reset', async () => {
    vi.mocked(readerApi.list).mockResolvedValue([makeReaderDoc({ id: 1 })])
    const reader = useReaderStore()
    await reader.load()

    expect((await reader.get(1)).id).toBe(1)
    expect(readerApi.get).not.toHaveBeenCalled()

    reader.reset()
    expect(reader.items).toEqual([])
    expect(reader.loaded).toBe(false)
  })
})
