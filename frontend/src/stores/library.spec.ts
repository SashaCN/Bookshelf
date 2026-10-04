import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { libraryApi } from '@/api/library'
import { makeUserBook } from '@/test/fixtures'
import { useLibraryStore } from './library'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
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

  it('forgets everything on reset', async () => {
    vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook()])
    const library = useLibraryStore()
    await library.load()

    library.reset()

    expect(library.items).toEqual([])
    expect(library.loaded).toBe(false)
  })
})
