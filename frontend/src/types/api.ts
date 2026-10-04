export interface User {
  id: number
  name: string
  email: string
  timezone: string
}

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload extends LoginPayload {
  name: string
  timezone: string
}

export interface UpdateProfilePayload {
  name?: string
  timezone?: string
}

export type BookStatus = 'want' | 'reading' | 'finished' | 'abandoned'

export interface Book {
  id: number
  title: string
  subtitle: string | null
  authors: string[]
  isbn_13: string | null
  cover_url: string | null
  page_count: number | null
  published_year: number | null
  subjects: string[]
  source: 'openlibrary' | 'manual'
}

/** A book in the reader's own library, with their progress. */
export interface UserBook {
  id: number
  status: BookStatus
  /** The statuses this entry may move to; the server owns the transition rules. */
  allowed_statuses: BookStatus[]
  current_page: number
  total_pages: number | null
  progress_percent: number | null
  rating: number | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
  book: Book
}

/** One move of the bookmark in a book's reading journal. */
export interface ReadingLog {
  id: number
  from_page: number
  to_page: number
  /** Pages gained by this move; negative for a correction downwards. */
  pages: number
  /** The reader's own calendar day, as YYYY-MM-DD. */
  logged_on: string
  created_at: string
}

export interface ReadingLogsPage {
  data: ReadingLog[]
  links: { next: string | null }
}

export interface ProgressResult {
  data: UserBook
  meta: {
    /** Pages this update was worth; 0 when the page did not change, negative for a correction. */
    pages: number
    /** The bookmark is on the last page, so the book can be marked as finished. */
    reached_end: boolean
  }
}

/** A search result from the Open Library catalog. */
export interface CatalogEntry {
  work_key: string
  title: string
  subtitle: string | null
  authors: string[]
  cover_url: string | null
  page_count: number | null
  published_year: number | null
  subjects: string[]
  isbn_13: string | null
  in_library: boolean
}

export interface AddBookPayload {
  /** Add from Open Library... */
  openlibrary_work_key?: string
  /** ...or by hand. */
  title?: string
  authors?: string[]
  published_year?: number | null
  total_pages?: number | null
  status?: BookStatus
}

export interface UpdateBookPayload {
  status?: BookStatus
  rating?: number | null
  total_pages?: number
}
