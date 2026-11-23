/** Types of the "year in books" recap. */

/** "A year in books": everything in the reader's own calendar; a book read again counts once per finished pass. */
export interface YearRecap {
  year: number
  books_finished: number
  pages_read: number
  days_with_reading: number
  longest_streak_in_year: number
  quotes_added: number
  /** Reviews (posts) the reader published in the year. */
  reviews_written: number
  average_rating: number | null
  top_rated: { title: string; authors: string[]; rating: number; finished_at: string }[]
  longest_book: { title: string; authors: string[]; total_pages: number } | null
  /** Month number 1-12 with the most pages. */
  busiest_month: { month: number; pages: number } | null
  goal: { target: number; achieved: number } | null
  finished_books: { title: string; authors: string[]; finished_at: string; rating: number | null }[]
}

export interface YearRecapResponse {
  data: YearRecap
  meta: { first_year: number; current_year: number }
}
