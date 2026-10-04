import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { libraryApi } from '@/api/library'
import { makeUserBook } from '@/test/fixtures'
import { useLibraryStore } from './library'

vi.mock('@/api/library', () => ({
  libraryApi: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
    progress: vi.fn(),
    logs: vi.fn(),
  },
  catalogApi: { search: vi.fn() },
}))

describe('library store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it('loads the library once and groups it by status', async () => {
    vi.mocked(libraryApi.list).mockResolvedValue([
      makeUserBook({ id: 1, status: 'reading' }),
      makeUserBook({ id: 2, status: 'want' }),
      makeUserBook({ id: 3, status: 'reading' }),
    ])
    const library = useLibraryStore()

    await library.load()
    await library.load()

    expect(libraryApi.list).toHaveBeenCalledTimes(1)
    expect(library.byStatus.reading.map((item) => item.id)).toEqual([1, 3])
    expect(library.byStatus.want).toHaveLength(1)
    expect(library.byStatus.finished).toEqual([])
  })

  it('reloads on demand', async () => {
    vi.mocked(libraryApi.list).mockResolvedValue([])
    const library = useLibraryStore()

    await library.load()
    await library.load(true)

    expect(libraryApi.list).toHaveBeenCalledTimes(2)
  })

  it('stays unloaded when loading fails, so it can be retried', async () => {
    vi.mocked(libraryApi.list).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce([])
    const library = useLibraryStore()

    await expect(library.load()).rejects.toThrow('offline')
    expect(library.loaded).toBe(false)

    await library.load()
    expect(library.loaded).toBe(true)
  })

  it('puts a newly added book first', async () => {
    vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1 })])
    vi.mocked(libraryApi.create).mockResolvedValue(makeUserBook({ id: 2 }))
    const library = useLibraryStore()
    await library.load()

    await library.add({ title: 'New' })

    expect(library.items.map((item) => item.id)).toEqual([2, 1])
  })

  it('replaces an updated book and moves it to the top', async () => {
    vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1 }), makeUserBook({ id: 2 })])
    vi.mocked(libraryApi.update).mockResolvedValue(makeUserBook({ id: 2, status: 'reading' }))
    const library = useLibraryStore()
    await library.load()

    await library.update(2, { status: 'reading' })

    expect(library.items.map((item) => item.id)).toEqual([2, 1])
    expect(library.find(2)?.status).toBe('reading')
    expect(library.items).toHaveLength(2)
  })

  it('removes a book', async () => {
    vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1 }), makeUserBook({ id: 2 })])
    vi.mocked(libraryApi.remove).mockResolvedValue(null)
    const library = useLibraryStore()
    await library.load()

    await library.remove(1)

    expect(library.items.map((item) => item.id)).toEqual([2])
  })

  describe('setProgress', () => {
    function reading(id: number, currentPage = 100) {
      return makeUserBook({
        id,
        status: 'reading',
        allowed_statuses: ['finished', 'abandoned'],
        current_page: currentPage,
        total_pages: 320,
        progress_percent: Math.floor((currentPage / 320) * 100),
      })
    }

    function deferred<T>() {
      let resolve!: (value: T) => void
      let reject!: (reason: unknown) => void
      const promise = new Promise<T>((res, rej) => {
        resolve = res
        reject = rej
      })
      return { promise, resolve, reject }
    }

    it('shows the new page of a book being read before the server answers', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1)])
      vi.mocked(libraryApi.progress).mockReturnValue(new Promise(() => {}))
      const library = useLibraryStore()
      await library.load()

      void library.setProgress(1, 130)

      expect(library.find(1)?.current_page).toBe(130)
      expect(library.find(1)?.progress_percent).toBe(40)
    })

    it('takes the server\'s copy of the book once it answers', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1)])
      vi.mocked(libraryApi.progress).mockResolvedValue({
        data: { ...reading(1, 130), rating: 5 },
        meta: { pages: 30, reached_end: false },
      })
      const library = useLibraryStore()
      await library.load()

      const result = await library.setProgress(1, 130)

      expect(result.meta.pages).toBe(30)
      expect(library.find(1)?.rating).toBe(5)
    })

    it('keeps the book where it is in the list', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1), reading(2), reading(3)])
      vi.mocked(libraryApi.progress).mockResolvedValue({ data: reading(2, 150), meta: { pages: 50, reached_end: false } })
      const library = useLibraryStore()
      await library.load()

      await library.setProgress(2, 150)

      expect(library.items.map((item) => item.id)).toEqual([1, 2, 3])
    })

    it('sends one request at a time for a book, so a quick second tap builds on the first', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1)])
      const first = deferred<Awaited<ReturnType<typeof libraryApi.progress>>>()
      vi.mocked(libraryApi.progress)
        .mockReturnValueOnce(first.promise)
        .mockResolvedValueOnce({ data: reading(1, 120), meta: { pages: 10, reached_end: false } })
      const library = useLibraryStore()
      await library.load()

      const one = library.setProgress(1, 110)
      const two = library.setProgress(1, 120)
      await Promise.resolve()

      expect(libraryApi.progress).toHaveBeenCalledTimes(1)
      expect(library.find(1)?.current_page).toBe(120)

      first.resolve({ data: reading(1, 110), meta: { pages: 10, reached_end: false } })
      await Promise.all([one, two])

      expect(libraryApi.progress).toHaveBeenNthCalledWith(1, 1, 110)
      expect(libraryApi.progress).toHaveBeenNthCalledWith(2, 1, 120)
      expect(library.find(1)?.current_page).toBe(120)
    })

    it('does not flash back to an older page while a later tap is still on its way', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1)])
      const second = deferred<Awaited<ReturnType<typeof libraryApi.progress>>>()
      vi.mocked(libraryApi.progress)
        .mockResolvedValueOnce({ data: reading(1, 110), meta: { pages: 10, reached_end: false } })
        .mockReturnValueOnce(second.promise)
      const library = useLibraryStore()
      await library.load()

      const one = library.setProgress(1, 110)
      void library.setProgress(1, 120)
      await one

      expect(library.find(1)?.current_page).toBe(120)
    })

    it('goes back to what the server says when it turns the update down', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1)])
      vi.mocked(libraryApi.progress).mockRejectedValue(new Error('rejected'))
      vi.mocked(libraryApi.get).mockResolvedValue(reading(1, 100))
      const library = useLibraryStore()
      await library.load()

      await expect(library.setProgress(1, 130)).rejects.toThrow('rejected')

      expect(library.find(1)?.current_page).toBe(100)
    })

    it('goes back to how the book was when the server cannot be asked either', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1)])
      vi.mocked(libraryApi.progress).mockRejectedValue(new Error('offline'))
      vi.mocked(libraryApi.get).mockRejectedValue(new Error('offline'))
      const library = useLibraryStore()
      await library.load()

      await expect(library.setProgress(1, 130)).rejects.toThrow('offline')

      expect(library.find(1)?.current_page).toBe(100)
    })

    it('still sends the next tap after one failed', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([reading(1)])
      vi.mocked(libraryApi.progress)
        .mockRejectedValueOnce(new Error('hiccup'))
        .mockResolvedValueOnce({ data: reading(1, 120), meta: { pages: 20, reached_end: false } })
      vi.mocked(libraryApi.get).mockResolvedValue(reading(1, 100))
      const library = useLibraryStore()
      await library.load()

      const failing = library.setProgress(1, 110).catch(() => 'failed')
      const next = library.setProgress(1, 120)

      expect(await failing).toBe('failed')
      await next
      expect(library.find(1)?.current_page).toBe(120)
    })

    it('waits for the server before changing a book that is not being read yet', async () => {
      vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1, status: 'want' })])
      vi.mocked(libraryApi.progress).mockReturnValue(new Promise(() => {}))
      const library = useLibraryStore()
      await library.load()

      void library.setProgress(1, 20)

      expect(library.find(1)?.status).toBe('want')
      expect(library.find(1)?.current_page).toBe(0)
    })
  })

  it('forgets everything on reset', async () => {
    vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook()])
    const library = useLibraryStore()
    await library.load()

    library.reset()

    expect(library.items).toEqual([])
    expect(library.loaded).toBe(false)
  })
})
