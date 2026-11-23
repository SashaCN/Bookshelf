import type { BookText, Chapter, PositionPayload, PositionResult } from '@/types/reader'
import { http, type RequestOptions } from './http'

export const readerApi = {
  /** Upload an EPUB for a library entry: the server reads it, keeps the cleaned-up text and answers with its contents. */
  upload: (userBookId: number, file: File, timeoutMs?: number) => {
    const form = new FormData()
    form.append('file', file)

    return http.upload<{ data: BookText }>(`/api/library/${userBookId}/text`, form, timeoutMs).then((response) => response.data)
  },
  /** The text of a library entry: its contents and where the reader is. Answers 404 when there is none. */
  text: (userBookId: number) => http.get<{ data: BookText }>(`/api/library/${userBookId}/text`).then((response) => response.data),
  removeText: (userBookId: number) => http.delete<null>(`/api/library/${userBookId}/text`),
  chapter: (textId: number, position: number) =>
    http.get<{ data: Chapter }>(`/api/book-texts/${textId}/chapters/${position}`).then((response) => response.data),
  /** `keepalive` for a position sent as the page is left, so that the request is not cut off with it. */
  savePosition: (textId: number, payload: PositionPayload, options?: Pick<RequestOptions, 'keepalive'>) => {
    const path = `/api/book-texts/${textId}/position`

    return options ? http.put<PositionResult>(path, payload, options) : http.put<PositionResult>(path, payload)
  },
}
