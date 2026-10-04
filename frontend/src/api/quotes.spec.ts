import { beforeEach, describe, expect, it, vi } from 'vitest'
import { makeQuote } from '@/test/fixtures'
import { http } from './http'
import { quotesApi } from './quotes'

vi.mock('./http', () => ({
  http: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
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

  it('unwraps the quote of the day, which may be missing', async () => {
    vi.mocked(http.get).mockResolvedValueOnce({ data: makeQuote({ id: 4 }) }).mockResolvedValueOnce({ data: null })

    expect((await quotesApi.daily())?.id).toBe(4)
    expect(await quotesApi.daily()).toBeNull()
    expect(http.get).toHaveBeenCalledWith('/api/quotes/daily')
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
