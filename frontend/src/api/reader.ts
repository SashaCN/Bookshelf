import type { ReaderChapter, ReaderDocument, ReaderPosition } from '@/types/api'
import { http } from './http'

export const readerApi = {
  list: () => http.get<{ data: ReaderDocument[] }>('/api/reader').then((response) => response.data),
  get: (id: number) => http.get<{ data: ReaderDocument }>(`/api/reader/${id}`).then((response) => response.data),
  upload: (file: File) => {
    const form = new FormData()
    form.append('file', file)
    return http.post<{ data: ReaderDocument }>('/api/reader', form).then((response) => response.data)
  },
  remove: (id: number) => http.delete<null>(`/api/reader/${id}`),
  savePosition: (id: number, position: ReaderPosition) =>
    http.put<{ data: ReaderDocument }>(`/api/reader/${id}/position`, position).then((response) => response.data),
  link: (id: number, userBookId: number | null) =>
    http.patch<{ data: ReaderDocument }>(`/api/reader/${id}`, { user_book_id: userBookId }).then((response) => response.data),
  chapter: (id: number, index: number) =>
    http.get<{ data: ReaderChapter }>(`/api/reader/${id}/chapters/${index}`).then((response) => response.data),
}
