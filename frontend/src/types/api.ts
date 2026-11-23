export interface User {
  id: number
  name: string
  email: string
  timezone: string
  username: string | null
  is_public: boolean
  /** Public readers who followed since the list was last opened; the server caps it at 99. */
  new_followers_count: number
}

export interface LoginPayload {
  email: string
  password: string
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface RegisterPayload extends LoginPayload {
  name: string
  timezone: string
}

export interface UpdateProfilePayload {
  name?: string
  timezone?: string
  username?: string | null
  is_public?: boolean
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
/** An earlier, finished pass through a book that was read again. */
export interface ReadPass {
  started_at: string | null
  finished_at: string
}

export interface UserBook {
  id: number
  status: BookStatus
  /** The statuses this entry may move to; the server owns the transition rules. */
  allowed_statuses: BookStatus[]
  current_page: number
  total_pages: number | null
  progress_percent: number | null
  rating: number | null
  /** The reader's text about the book; only written for a finished or abandoned one. */
  review: string | null
  review_visibility: QuoteVisibility
  reviewed_at: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string | null
  book: Book
  /** The linked EPUB read most recently; only in the response for a single entry, not in the list. */
  reader_document_id?: number | null
  /** Finished passes through the book, the current one included when it is finished. */
  times_read: number
  /** Archived earlier passes, newest first; only in the response for a single entry, not in the list. */
  reads?: ReadPass[]
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
  /** An empty string or null clears the review. */
  review?: string | null
  review_visibility?: QuoteVisibility
  total_pages?: number
}

export type QuoteType = 'quote' | 'insight'
export type QuoteVisibility = 'private' | 'public'

/** A passage (or the reader's own insight) saved from a book. */
export interface Quote {
  id: number
  type: QuoteType
  content: string
  /** The reader's own thought about it. */
  note: string | null
  page: number | null
  is_favorite: boolean
  visibility: QuoteVisibility
  /** Present in the lists: the own quotes and quotes of other readers. */
  likes_count?: number
  /** Present in the lists of other readers' quotes: the comments the viewer can see. */
  comments_count?: number
  /** Present on quotes of other readers; always false for a guest. */
  liked_by_me?: boolean
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
  visibility?: QuoteVisibility
}

export interface QuoteFilters {
  favorite?: boolean
  /** The id of a library entry (user book). */
  book?: number
  type?: QuoteType
}

/** A public review of a book, as other readers see it. */
export interface PublicReview {
  id: number
  book: { title: string; authors: string[]; cover_url: string | null }
  rating: number | null
  review: string
  reviewed_at: string
}

export interface ReviewsPage {
  data: PublicReview[]
  links: { next: string | null }
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
  /** Activity in the built-in EPUB reader, in the reader's time zone. */
  reader: { chapters_this_month: number; chapters_this_year: number; documents_in_progress: number }
}

export interface DailyPages {
  date: string
  pages: number
  /** EPUB chapters read that day (the server always sends it; the pages chart does not use it). */
  chapters?: number
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

export interface PublicProfile {
  username: string
  name: string
  books_finished_this_year: number
  followers_count: number
  following_count: number
  /** Public reviews only. */
  reviews_count: number
  is_following: boolean
  is_me: boolean
}

/** A reader as listed among followers or followed. */
export interface PersonSummary {
  username: string
  name: string
  /** Only in the reader's own followers list: followed since they last looked. */
  is_new?: boolean
}

/** A person found by the people search, with whether the reader already follows them. */
export interface PersonSearchResult extends PersonSummary {
  is_following: boolean
}

export interface PeoplePage {
  data: PersonSummary[]
  links: { next: string | null }
}

/** A public quote in the feed, with whoever saved it. */
export interface FeedQuote extends Quote {
  author: PersonSummary
}

/** A comment on a public quote. An author without a public profile is anonymous: no handle, no name. */
export interface QuoteComment {
  id: number
  body: string
  created_at: string
  author: { username: string | null; name: string | null }
  can_delete: boolean
}

export interface QuoteCommentsPage {
  data: QuoteComment[]
  links: { next: string | null }
}

export interface FeedPage {
  data: FeedQuote[]
  links: { next: string | null }
}

export interface ReaderPosition {
  chapter: number
  fraction: number
}

/** A chapter named in the book's table of contents; `index` is the position in the spine. */
export interface ReaderTocEntry {
  index: number
  title: string
}

/** An uploaded EPUB. `progress` is a percentage (0..100) derived from the saved position. */
export interface ReaderDocument {
  id: number
  title: string
  author: string | null
  /** The library entry this document is linked to; only a link, nothing is synced. */
  user_book_id: number | null
  user_book: { id: number; title: string } | null
  chapters_count: number
  position: ReaderPosition
  progress: number
  /** When the position was last saved; null for a book that has not been opened yet. */
  last_read_at: string | null
  created_at: string
  /** Only in the response for a single book, not in the list. */
  toc?: ReaderTocEntry[]
}

/** A bookmark of a chapter (scroll inside a chapter is not tracked), with an optional short note. */
export interface ReaderBookmark {
  id: number
  chapter: number
  chapter_title: string | null
  note: string | null
  created_at: string
}

/** One chapter; `html` is untrusted markup and must only ever be shown in a sandboxed iframe. */
export interface ReaderChapter {
  index: number
  count: number
  title: string | null
  html: string
}
