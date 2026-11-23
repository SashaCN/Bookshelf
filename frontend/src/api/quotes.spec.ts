import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeQuote } from '@/test/fixtures'
import { http } from './http'
import { quotesApi } from './quotes'

vi.mock('./http', () => ({
  http: { download: vi.fn(), get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

describe('quotesApi', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('asks for the first page without filters by default', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })

    await quotesApi.list()

    expect(http.get).toHaveBeenCalledWith('/api/quotes?page=1')
  })

  it('sends only the filters that are set', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })

    await quotesApi.list({ favorite: true, book: 7, type: 'insight' }, 3)
    await quotesApi.list({ favorite: false, book: 7 }, 2)

    expect(http.get).toHaveBeenNthCalledWith(1, '/api/quotes?favorite=1&book=7&type=insight&page=3')
    expect(http.get).toHaveBeenNthCalledWith(2, '/api/quotes?book=7&page=2')
  })

  it('sends the search with the other filters, in the list and in the export', async () => {
    vi.mocked(http.get).mockResolvedValue({ data: [], links: { next: null } })
    vi.mocked(http.download).mockResolvedValue(new Blob([]))

    await quotesApi.list({ favorite: true, q: 'щастя тут' }, 2)
    await quotesApi.exportMarkdown({ q: 'щастя' })

    expect(http.get).toHaveBeenCalledWith('/api/quotes?favorite=1&q=%D1%89%D0%B0%D1%81%D1%82%D1%8F+%D1%82%D1%83%D1%82&page=2')
    expect(http.download).toHaveBeenCalledWith('/api/quotes/export?q=%D1%89%D0%B0%D1%81%D1%82%D1%8F')
  })

  it('unwraps the quote of the day, which may be missing', async () => {
    vi.mocked(http.get).mockResolvedValueOnce({ data: makeQuote({ id: 4 }) }).mockResolvedValueOnce({ data: null })

    expect((await quotesApi.daily())?.id).toBe(4)
    expect(await quotesApi.daily()).toBeNull()
    expect(http.get).toHaveBeenCalledWith('/api/quotes/daily')
  })

  it('downloads the export with the filters that are set and without paging', async () => {
    const file = new Blob(['# Мої цитати'])
    vi.mocked(http.download).mockResolvedValue(file)

    expect(await quotesApi.exportMarkdown({ favorite: true, book: 7 })).toBe(file)
    await quotesApi.exportMarkdown()

    expect(http.download).toHaveBeenNthCalledWith(1, '/api/quotes/export?favorite=1&book=7')
    expect(http.download).toHaveBeenNthCalledWith(2, '/api/quotes/export')
  })

  it('creates a quote under its library entry and unwraps the answer', async () => {
    vi.mocked(http.post).mockResolvedValue({ data: makeQuote({ id: 9 }) })

    const quote = await quotesApi.create(3, { content: 'Text' })

    expect(http.post).toHaveBeenCalledWith('/api/library/3/quotes', { content: 'Text' })
    expect(quote.id).toBe(9)
  })

  it('updates, reads and removes a quote by its id', async () => {
    vi.mocked(http.patch).mockResolvedValue({ data: makeQuote({ id: 5, is_favorite: true }) })
    vi.mocked(http.get).mockResolvedValue({ data: makeQuote({ id: 5 }) })
    vi.mocked(http.delete).mockResolvedValue(null)

    expect((await quotesApi.update(5, { is_favorite: true })).is_favorite).toBe(true)
    expect((await quotesApi.get(5)).id).toBe(5)
    await quotesApi.remove(5)

    expect(http.patch).toHaveBeenCalledWith('/api/quotes/5', { is_favorite: true })
    expect(http.get).toHaveBeenCalledWith('/api/quotes/5')
    expect(http.delete).toHaveBeenCalledWith('/api/quotes/5')
  })
})
