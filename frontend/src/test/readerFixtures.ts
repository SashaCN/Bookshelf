import type { ReaderBookmark, ReaderChapter, ReaderDocument } from '@/types/api'

export function makeReaderDoc(overrides: Partial<ReaderDocument> = {}): ReaderDocument {
  return {
    id: 1,
    title: 'Кобзар',
    author: 'Тарас Шевченко',
    user_book_id: null,
    user_book: null,
    chapters_count: 12,
    position: { chapter: 0, fraction: 0 },
    progress: 0,
    last_read_at: null,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

export function makeReaderChapter(overrides: Partial<ReaderChapter> = {}): ReaderChapter {
  return { index: 0, count: 12, title: null, html: '<p>Текст розділу</p>', ...overrides }
}

export function makeReaderBookmark(overrides: Partial<ReaderBookmark> = {}): ReaderBookmark {
  return { id: 1, chapter: 0, chapter_title: null, note: null, created_at: '2026-01-01T00:00:00Z', ...overrides }
}
