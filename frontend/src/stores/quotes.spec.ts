import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { quotesApi } from '@/api/quotes'
import { makeQuote } from '@/test/fixtures'
import type { QuotesPage } from '@/types/api'
import { useQuotesStore } from './quotes'

vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

function page(ids: number[], next: string | null = null): QuotesPage {
  return { data: ids.map((id) => makeQuote({ id })), links: { next } }
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

describe('quotes store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  describe('the list', () => {
    it('loads the first page for the given filters', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([3, 2], '/api/quotes?page=2'))
      const quotes = useQuotesStore()

      await quotes.load({ favorite: true, book: 4 })

      expect(quotesApi.list).toHaveBeenCalledWith({ favorite: true, book: 4 }, 1)
      expect(quotes.items.map((item) => item.id)).toEqual([3, 2])
      expect(quotes.hasMore).toBe(true)
      expect(quotes.loaded).toBe(true)
    })

    it('appends the next page on "load more" and stops after the last one', async () => {
      vi.mocked(quotesApi.list)
        .mockResolvedValueOnce(page([3, 2], '/api/quotes?page=2'))
        .mockResolvedValueOnce(page([1], null))
      const quotes = useQuotesStore()
      await quotes.load({ book: 4 })

      await quotes.loadMore()

      expect(quotesApi.list).toHaveBeenLastCalledWith({ book: 4 }, 2)
      expect(quotes.items.map((item) => item.id)).toEqual([3, 2, 1])
      expect(quotes.hasMore).toBe(false)

      await quotes.loadMore()
      expect(quotesApi.list).toHaveBeenCalledTimes(2)
    })

    it('does not show a quote twice when a new one has pushed it onto the next page', async () => {
      vi.mocked(quotesApi.list)
        .mockResolvedValueOnce(page([3, 2], '/api/quotes?page=2'))
        .mockResolvedValueOnce(page([2, 1], null))
      const quotes = useQuotesStore()
      await quotes.load()

      await quotes.loadMore()

      expect(quotes.items.map((item) => item.id)).toEqual([3, 2, 1])
    })

    it('asks for one more page at a time', async () => {
      const second = deferred<QuotesPage>()
      vi.mocked(quotesApi.list).mockResolvedValueOnce(page([2], '/api/quotes?page=2')).mockReturnValueOnce(second.promise)
      const quotes = useQuotesStore()
      await quotes.load()

      const first = quotes.loadMore()
      void quotes.loadMore()

      expect(quotes.loadingMore).toBe(true)
      expect(quotesApi.list).toHaveBeenCalledTimes(2)

      second.resolve(page([1]))
      await first
      expect(quotes.loadingMore).toBe(false)
    })

    it('keeps what is loaded when "load more" fails, so it can be tried again', async () => {
      vi.mocked(quotesApi.list)
        .mockResolvedValueOnce(page([2], '/api/quotes?page=2'))
        .mockRejectedValueOnce(new Error('offline'))
        .mockResolvedValueOnce(page([1]))
      const quotes = useQuotesStore()
      await quotes.load()

      await expect(quotes.loadMore()).rejects.toThrow('offline')
      expect(quotes.items.map((item) => item.id)).toEqual([2])
      expect(quotes.hasMore).toBe(true)

      await quotes.loadMore()
      expect(quotesApi.list).toHaveBeenLastCalledWith({}, 2)
      expect(quotes.items.map((item) => item.id)).toEqual([2, 1])
    })

    it('empties the list at once when the filters change', async () => {
      vi.mocked(quotesApi.list).mockResolvedValueOnce(page([1])).mockReturnValueOnce(new Promise(() => {}))
      const quotes = useQuotesStore()
      await quotes.load()

      void quotes.load({ favorite: true })

      expect(quotes.items).toEqual([])
      expect(quotes.loaded).toBe(false)
    })

    it('keeps showing the list while the same filters refresh', async () => {
      vi.mocked(quotesApi.list).mockResolvedValueOnce(page([1])).mockReturnValueOnce(new Promise(() => {}))
      const quotes = useQuotesStore()
      await quotes.load({ book: 2 })

      void quotes.load({ book: 2 })

      expect(quotes.items.map((item) => item.id)).toEqual([1])
      expect(quotes.loaded).toBe(true)
    })

    it('drops the answer for filters the reader has already left', async () => {
      const slow = deferred<QuotesPage>()
      vi.mocked(quotesApi.list).mockReturnValueOnce(slow.promise).mockResolvedValueOnce(page([7]))
      const quotes = useQuotesStore()

      const stale = quotes.load({ favorite: true })
      await quotes.load({})
      slow.resolve(page([1, 2, 3]))
      await stale

      expect(quotes.items.map((item) => item.id)).toEqual([7])
    })

    it('does not report a failure of a request that is already out of date', async () => {
      const slow = deferred<QuotesPage>()
      vi.mocked(quotesApi.list).mockReturnValueOnce(slow.promise).mockResolvedValueOnce(page([7]))
      const quotes = useQuotesStore()

      const stale = quotes.load({ favorite: true })
      await quotes.load({})
      slow.reject(new Error('late'))

      await expect(stale).resolves.toBeUndefined()
      expect(quotes.items.map((item) => item.id)).toEqual([7])
    })

    it('stays unloaded and throws when loading fails, so it can be retried', async () => {
      vi.mocked(quotesApi.list).mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce(page([1]))
      const quotes = useQuotesStore()

      await expect(quotes.load()).rejects.toThrow('offline')
      expect(quotes.loaded).toBe(false)

      await quotes.load()
      expect(quotes.loaded).toBe(true)
    })
  })

  describe('changes made elsewhere', () => {
    it('puts a new quote first in a list it belongs to', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([1]))
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 2, user_book_id: 4 }))
      const quotes = useQuotesStore()
      await quotes.load({ book: 4 })

      const created = await quotes.create(4, { content: 'Text' })

      expect(quotesApi.create).toHaveBeenCalledWith(4, { content: 'Text' })
      expect(created.id).toBe(2)
      expect(quotes.items.map((item) => item.id)).toEqual([2, 1])
    })

    it('leaves a new quote out of a list it does not belong to', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([1]))
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 2, user_book_id: 5, is_favorite: false }))
      const quotes = useQuotesStore()

      await quotes.load({ book: 4 })
      await quotes.create(5, { content: 'Text' })
      expect(quotes.items.map((item) => item.id)).toEqual([1])

      await quotes.load({ favorite: true })
      await quotes.create(5, { content: 'Text' })
      expect(quotes.items.map((item) => item.id)).toEqual([1])
    })

    it('does not start a list by itself when none has been loaded', async () => {
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 2 }))
      const quotes = useQuotesStore()

      await quotes.create(1, { content: 'Text' })

      expect(quotes.items).toEqual([])
    })

    it('replaces an edited quote in place, in the list and as the quote of the day', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([3, 2, 1]))
      vi.mocked(quotesApi.daily).mockResolvedValue(makeQuote({ id: 2 }))
      vi.mocked(quotesApi.update).mockResolvedValue(makeQuote({ id: 2, content: 'Edited' }))
      const quotes = useQuotesStore()
      await quotes.load()
      await quotes.loadDaily()

      await quotes.update(2, { content: 'Edited' })

      expect(quotesApi.update).toHaveBeenCalledWith(2, { content: 'Edited' })
      expect(quotes.items.map((item) => item.id)).toEqual([3, 2, 1])
      expect(quotes.find(2)?.content).toBe('Edited')
      expect(quotes.daily?.content).toBe('Edited')
    })

    it('removes a quote from the list and from the quote of the day', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([2, 1]))
      vi.mocked(quotesApi.daily).mockResolvedValue(makeQuote({ id: 2 }))
      vi.mocked(quotesApi.remove).mockResolvedValue(null)
      const quotes = useQuotesStore()
      await quotes.load()
      await quotes.loadDaily()

      await quotes.remove(2)

      expect(quotesApi.remove).toHaveBeenCalledWith(2)
      expect(quotes.items.map((item) => item.id)).toEqual([1])
      expect(quotes.daily).toBeNull()
    })

    it('keeps the quote when the server refuses to remove it', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([1]))
      vi.mocked(quotesApi.remove).mockRejectedValue(new ApiError(500, null))
      const quotes = useQuotesStore()
      await quotes.load()

      await expect(quotes.remove(1)).rejects.toBeInstanceOf(ApiError)

      expect(quotes.items).toHaveLength(1)
    })
  })

  describe('toggleFavorite', () => {
    it('shows the star at once, before the server answers', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([1]))
      vi.mocked(quotesApi.update).mockReturnValue(new Promise(() => {}))
      const quotes = useQuotesStore()
      await quotes.load()

      void quotes.toggleFavorite(quotes.items[0]!)

      expect(quotes.find(1)?.is_favorite).toBe(true)
      expect(quotesApi.update).toHaveBeenCalledWith(1, { is_favorite: true })
    })

    it('takes the server\'s copy once it answers, without moving the quote', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([2, 1]))
      vi.mocked(quotesApi.update).mockResolvedValue(makeQuote({ id: 2, is_favorite: true, note: 'From the server' }))
      const quotes = useQuotesStore()
      await quotes.load()

      await quotes.toggleFavorite(quotes.items[0]!)

      expect(quotes.items.map((item) => item.id)).toEqual([2, 1])
      expect(quotes.find(2)?.note).toBe('From the server')
    })

    it('un-stars a favorite quote', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue({ data: [makeQuote({ id: 1, is_favorite: true })], links: { next: null } })
      vi.mocked(quotesApi.update).mockResolvedValue(makeQuote({ id: 1, is_favorite: false }))
      const quotes = useQuotesStore()
      await quotes.load()

      await quotes.toggleFavorite(quotes.items[0]!)

      expect(quotesApi.update).toHaveBeenCalledWith(1, { is_favorite: false })
      expect(quotes.find(1)?.is_favorite).toBe(false)
    })

    it('puts the star back and throws when the server turns it down', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([1]))
      vi.mocked(quotesApi.update).mockRejectedValue(new ApiError(500, null))
      const quotes = useQuotesStore()
      await quotes.load()

      await expect(quotes.toggleFavorite(quotes.items[0]!)).rejects.toBeInstanceOf(ApiError)

      expect(quotes.find(1)?.is_favorite).toBe(false)
    })

    it('changes the quote of the day too', async () => {
      vi.mocked(quotesApi.daily).mockResolvedValue(makeQuote({ id: 5 }))
      vi.mocked(quotesApi.update).mockReturnValue(new Promise(() => {}))
      const quotes = useQuotesStore()
      await quotes.loadDaily()

      void quotes.toggleFavorite(quotes.daily!)

      expect(quotes.daily?.is_favorite).toBe(true)
    })

    it('puts the star of the quote of the day back when the request fails', async () => {
      vi.mocked(quotesApi.daily).mockResolvedValue(makeQuote({ id: 5 }))
      vi.mocked(quotesApi.update).mockRejectedValue(new Error('offline'))
      const quotes = useQuotesStore()
      await quotes.loadDaily()

      await expect(quotes.toggleFavorite(quotes.daily!)).rejects.toThrow('offline')

      expect(quotes.daily?.is_favorite).toBe(false)
    })
  })

  describe('the quote of the day', () => {
    it('loads it', async () => {
      vi.mocked(quotesApi.daily).mockResolvedValue(makeQuote({ id: 8 }))
      const quotes = useQuotesStore()

      await quotes.loadDaily()

      expect(quotes.daily?.id).toBe(8)
    })

    it('is empty while the reader has no quotes', async () => {
      vi.mocked(quotesApi.daily).mockResolvedValue(null)
      const quotes = useQuotesStore()

      await quotes.loadDaily()

      expect(quotes.daily).toBeNull()
    })
  })

  describe('get', () => {
    it('answers from the list or the quote of the day without asking the server', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue(page([1]))
      vi.mocked(quotesApi.daily).mockResolvedValue(makeQuote({ id: 9 }))
      const quotes = useQuotesStore()
      await quotes.load()
      await quotes.loadDaily()

      expect((await quotes.get(1)).id).toBe(1)
      expect((await quotes.get(9)).id).toBe(9)
      expect(quotesApi.get).not.toHaveBeenCalled()
    })

    it('asks the server for any other quote', async () => {
      vi.mocked(quotesApi.get).mockResolvedValue(makeQuote({ id: 6 }))
      const quotes = useQuotesStore()

      expect((await quotes.get(6)).id).toBe(6)
      expect(quotesApi.get).toHaveBeenCalledWith(6)
    })
  })

  it('forgets everything on reset', async () => {
    vi.mocked(quotesApi.list).mockResolvedValue(page([1], '/api/quotes?page=2'))
    vi.mocked(quotesApi.daily).mockResolvedValue(makeQuote())
    const quotes = useQuotesStore()
    await quotes.load({ favorite: true })
    await quotes.loadDaily()

    quotes.reset()

    expect(quotes.items).toEqual([])
    expect(quotes.filters).toEqual({})
    expect(quotes.hasMore).toBe(false)
    expect(quotes.loaded).toBe(false)
    expect(quotes.daily).toBeNull()
  })

  it('ignores an answer that arrives after a reset', async () => {
    const slow = deferred<QuotesPage>()
    vi.mocked(quotesApi.list).mockReturnValue(slow.promise)
    const quotes = useQuotesStore()

    const pending = quotes.load()
    quotes.reset()
    slow.resolve(page([1]))
    await pending

    expect(quotes.items).toEqual([])
    expect(quotes.loaded).toBe(false)
  })
})
