import type { GoodreadsImportResult } from '@/types/libraryImport'
import type {
  AddBookPayload,
  CatalogEntry,
  ProgressResult,
  ReadingLogsPage,
  UpdateBookPayload,
  UserBook,
} from '@/types/api'
import { http } from './http'

export const libraryApi = {
  list: () => http.get<{ data: UserBook[] }>('/api/library').then((response) => response.data),
  get: (id: number) => http.get<{ data: UserBook }>(`/api/library/${id}`).then((response) => response.data),
  create: (payload: AddBookPayload) =>
    http.post<{ data: UserBook }>('/api/library', payload).then((response) => response.data),
  update: (id: number, payload: UpdateBookPayload) =>
    http.patch<{ data: UserBook }>(`/api/library/${id}`, payload).then((response) => response.data),
  remove: (id: number) => http.delete<null>(`/api/library/${id}`),
  progress: (id: number, page: number) => http.post<ProgressResult>(`/api/library/${id}/progress`, { page }),
  reread: (id: number) =>
    http.post<{ data: UserBook }>(`/api/library/${id}/reread`).then((response) => response.data),
  logs: (id: number, page = 1) => http.get<ReadingLogsPage>(`/api/library/${id}/logs?page=${page}`),
  importGoodreads: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return http.post<{ data: GoodreadsImportResult }>('/api/library/import', form).then((response) => response.data)
  },
}

/** Spec 5.1.3: the server gives Open Library 3 s; this leaves it a second to answer "unavailable" before we give up. */
export const CATALOG_SEARCH_TIMEOUT_MS = 4000

export const catalogApi = {
  search: (query: string) =>
    http
      .get<{ data: CatalogEntry[] }>(`/api/catalog/search?q=${encodeURIComponent(query)}`, { timeoutMs: CATALOG_SEARCH_TIMEOUT_MS })
      .then((response) => response.data),
}
