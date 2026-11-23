import type { Quote, QuoteFilters, QuotePayload, QuotesPage } from '@/types/api'
import type { TagCount } from '@/types/quotes'
import { http } from './http'

function filterParams(filters: QuoteFilters): URLSearchParams {
  const params = new URLSearchParams()
  if (filters.favorite) params.set('favorite', '1')
  if (filters.book !== undefined) params.set('book', String(filters.book))
  if (filters.type) params.set('type', filters.type)
  if (filters.q) params.set('q', filters.q)
  if (filters.tag) params.set('tag', filters.tag)
  return params
}

function listQuery(filters: QuoteFilters, page: number): string {
  const params = filterParams(filters)
  params.set('page', String(page))
  return params.toString()
}

export const quotesApi = {
  list: (filters: QuoteFilters = {}, page = 1) => http.get<QuotesPage>(`/api/quotes?${listQuery(filters, page)}`),
  /** The reader's tags with their quote counts, ordered by name. */
  tags: () => http.get<{ data: TagCount[] }>('/api/tags').then((response) => response.data),
  /** The quote of the day, or null while the reader has no quotes. */
  daily: () => http.get<{ data: Quote | null }>('/api/quotes/daily?favorite=1').then((response) => response.data),
  get: (id: number) => http.get<{ data: Quote }>(`/api/quotes/${id}`).then((response) => response.data),
  /** The quotes matching the filters as a Markdown file. */
  exportMarkdown: (filters: QuoteFilters = {}) => {
    const query = filterParams(filters).toString()
    return http.download(`/api/quotes/export${query ? `?${query}` : ''}`)
  },
  create: (userBookId: number, payload: QuotePayload) =>
    http.post<{ data: Quote }>(`/api/library/${userBookId}/quotes`, payload).then((response) => response.data),
  update: (id: number, payload: Partial<QuotePayload>) =>
    http.patch<{ data: Quote }>(`/api/quotes/${id}`, payload).then((response) => response.data),
  remove: (id: number) => http.delete<null>(`/api/quotes/${id}`),
}
