import type { BookText, Chapter, ChapterSummary, Highlight } from '@/types/reader'

export function makeChapterSummary(overrides: Partial<ChapterSummary> = {}): ChapterSummary {
  return { position: 0, title: 'Перший розділ', characters: 100, ...overrides }
}

export function makeBookText(overrides: Partial<BookText> = {}): BookText {
  return {
    id: 10,
    user_book_id: 1,
    source_name: 'book.epub',
    chapters_count: 3,
    characters_count: 300,
    position: { chapter: 0, chapter_progress: 0, updated_at: null },
    progress: 0,
    chapters: [
      makeChapterSummary({ position: 0, title: 'Перший розділ' }),
      makeChapterSummary({ position: 1, title: 'Second chapter' }),
      makeChapterSummary({ position: 2, title: null }),
    ],
    ...overrides,
  }
}

export function makeChapter(overrides: Partial<Chapter> = {}): Chapter {
  return {
    position: 0,
    title: 'Перший розділ',
    html: '<p>Hello brave new world.</p><p>Second paragraph 😀 here.</p>',
    characters: 47,
    highlights: [],
    ...overrides,
  }
}

export function makeHighlight(overrides: Partial<Highlight> = {}): Highlight {
  return { id: 5, start: 6, end: 11, content: 'brave', note: null, ...overrides }
}
