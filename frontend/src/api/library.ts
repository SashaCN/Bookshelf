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
  logs: (id: number, page = 1) => http.get<ReadingLogsPage>(`/api/library/${id}/logs?page=${page}`),
}

export const catalogApi = {
  search: (query: string) =>
    http
      .get<{ data: CatalogEntry[] }>(`/api/catalog/search?q=${encodeURIComponent(query)}`)
      .then((response) => response.data),
}
