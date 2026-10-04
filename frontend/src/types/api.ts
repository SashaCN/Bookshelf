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
    /** Days in a row with something read, counting the update just made. */
    streak: number
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

export type QuoteType = 'quote' | 'insight'

/** A passage (or the reader's own insight) saved from a book. */
export interface Quote {
  id: number
  type: QuoteType
  content: string
  /** The reader's own thought about it. */
  note: string | null
  page: number | null
  is_favorite: boolean
  created_at: string
  user_book_id: number
  book: { title: string; authors: string[]; cover_url: string | null }
}

/** What the reader enters for a quote; an update may send any subset of it. */
export interface QuotePayload {
  type?: QuoteType
  content: string
  note?: string | null
  page?: number | null
  is_favorite?: boolean
}

export interface QuoteFilters {
  favorite?: boolean
  /** The id of a library entry (user book). */
  book?: number
  type?: QuoteType
}

export interface QuotesPage {
  data: Quote[]
  links: { next: string | null }
}

export interface StreakStats {
  current: number
  longest: number
  /** Whether anything was read today; a streak that was not extended today is still alive until midnight. */
  read_today: boolean
}

/** When a book that is being read will be finished at the current pace. */
export interface BookForecast {
  user_book_id: number
  title: string
  current_page: number
  total_pages: number
  /** Null when nothing has been read lately. */
  pages_per_day: number | null
  days_left: number | null
  finish_on: string | null
}

export interface StatsSummary {
  today: { date: string; pages: number }
  streak: StreakStats
  /** Pages per day over the last 14 days. */
  pace: number
  month: { pages: number; books_finished: number }
  year: { pages: number; books_finished: number }
  forecasts: BookForecast[]
}

export interface DailyPages {
  date: string
  pages: number
}

export interface DailyStats {
  data: DailyPages[]
  meta: { from: string; to: string; total_pages: number }
}

/** The yearly goal "N books" and how the reader stands against it. */
export interface GoalProgress {
  year: number
  target_books: number | null
  finished_books: number
  expected_books: number | null
  /** Whole books that should be finished by today. */
  due_books: number | null
  remaining_books: number | null
  on_track: boolean | null
}
