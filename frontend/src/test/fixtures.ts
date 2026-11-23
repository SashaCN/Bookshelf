import type { Book, CatalogEntry, FeedQuote, PublicProfile, PublicReview, Quote, UserBook } from '@/types/api'

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
    review: null,
    review_visibility: 'private',
    reviewed_at: null,
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
    visibility: 'private',
    created_at: '2026-10-05T10:00:00+00:00',
    user_book_id: 1,
    book: { title: 'Atomic Habits', authors: ['James Clear'], cover_url: null },
    ...overrides,
  }
}

export function makeFeedQuote(overrides: Partial<FeedQuote> = {}): FeedQuote {
  return { ...makeQuote(), visibility: 'public', author: { username: 'olena', name: 'Олена' }, ...overrides }
}

export function makeProfile(overrides: Partial<PublicProfile> = {}): PublicProfile {
  return {
    username: 'olena',
    name: 'Олена',
    books_finished_this_year: 5,
    followers_count: 3,
    following_count: 2,
    reviews_count: 0,
    is_following: false,
    is_me: false,
    ...overrides,
  }
}

export function makePublicReview(overrides: Partial<PublicReview> = {}): PublicReview {
  return {
    id: 1,
    book: { title: 'Маленький принц', authors: ['Антуан де Сент-Екзюпері'], cover_url: null },
    rating: 5,
    review: 'Проста книга про складні речі.',
    reviewed_at: '2026-10-05T10:00:00+00:00',
    ...overrides,
  }
}
