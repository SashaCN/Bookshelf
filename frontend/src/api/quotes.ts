import type { Quote, QuoteFilters, QuotePayload, QuotesPage } from '@/types/api'
import { http } from './http'

function listQuery(filters: QuoteFilters, page: number): string {
  const params = new URLSearchParams()
  if (filters.favorite) params.set('favorite', '1')
  if (filters.book !== undefined) params.set('book', String(filters.book))
  if (filters.type) params.set('type', filters.type)
  params.set('page', String(page))
  return params.toString()
}

export const quotesApi = {
  list: (filters: QuoteFilters = {}, page = 1) => http.get<QuotesPage>(`/api/quotes?${listQuery(filters, page)}`),
  /** The quote of the day, or null while the reader has no quotes. */
  daily: () => http.get<{ data: Quote | null }>('/api/quotes/daily').then((response) => response.data),
  get: (id: number) => http.get<{ data: Quote }>(`/api/quotes/${id}`).then((response) => response.data),
  create: (userBookId: number, payload: QuotePayload) =>
    http.post<{ data: Quote }>(`/api/library/${userBookId}/quotes`, payload).then((response) => response.data),
  update: (id: number, payload: Partial<QuotePayload>) =>
    http.patch<{ data: Quote }>(`/api/quotes/${id}`, payload).then((response) => response.data),
  remove: (id: number) => http.delete<null>(`/api/quotes/${id}`),
}
