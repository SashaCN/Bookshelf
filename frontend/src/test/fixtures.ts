import type { Book, CatalogEntry, Quote, UserBook } from '@/types/api'

export function makeBook(overrides: Partial<Book> = {}): Book {
  return {
    id: 1,
    title: 'Atomic Habits',
    subtitle: null,
    authors: ['James Clear'],
    isbn_13: null,
    cover_url: null,
    page_count: 320,
    published_year: 2016,
    subjects: [],
    source: 'openlibrary',
    ...overrides,
  }
}

export function makeUserBook(overrides: Partial<UserBook> = {}): UserBook {
  return {
    id: 1,
    status: 'want',
    allowed_statuses: ['reading', 'finished'],
    current_page: 0,
    total_pages: 320,
    progress_percent: 0,
    rating: null,
    started_at: null,
    finished_at: null,
    created_at: '2026-10-05T10:00:00+00:00',
    book: makeBook(),
    ...overrides,
  }
}

export function makeEntry(overrides: Partial<CatalogEntry> = {}): CatalogEntry {
  return {
    work_key: '/works/OL17930368W',
    title: 'Atomic Habits',
    subtitle: null,
    authors: ['James Clear'],
    cover_url: null,
    page_count: 323,
    published_year: 2016,
    subjects: [],
    isbn_13: null,
    in_library: false,
    ...overrides,
  }
}

export function makeQuote(overrides: Partial<Quote> = {}): Quote {
  return {
    id: 1,
    type: 'quote',
    content: 'You do not rise to the level of your goals.',
    note: null,
    page: null,
    is_favorite: false,
    created_at: '2026-10-05T10:00:00+00:00',
    user_book_id: 1,
    book: { title: 'Atomic Habits', authors: ['James Clear'], cover_url: null },
    ...overrides,
  }
}
