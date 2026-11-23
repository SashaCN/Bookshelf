import type { UserBook } from '@/types/api'

export type LibrarySort = 'recent' | 'title' | 'author' | 'rating' | 'finished'

export const librarySorts: LibrarySort[] = ['recent', 'title', 'author', 'rating', 'finished']

export function isLibrarySort(value: unknown): value is LibrarySort {
  return typeof value === 'string' && (librarySorts as string[]).includes(value)
}

/** Lowercase, one kind of apostrophe, no accents, single spaces. */
export function normalize(text: string): string {
  return text
    .replace(/[’ʼ‘`´′]/g, "'")
    .normalize('NFKD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim()
}

/** Everything a book is searched by, normalized once. */
export function searchKey(userBook: UserBook): string {
  return normalize([userBook.book.title, userBook.book.subtitle ?? '', ...userBook.book.authors].join(' '))
}

/** Apostrophes are optional in matching, so «п'ять» finds «пять» and vice versa. */
function loose(text: string): string {
  return text.replace(/'/g, '')
}

/** Every word of the query must occur in the key, as a word prefix or as a substring. */
export function matches(key: string, query: string): boolean {
  const normalized = normalize(query)
  if (normalized === '') return true
  const looseKey = loose(key)
  return normalized.split(' ').every((word) => looseKey.includes(loose(word)))
}

const collator = new Intl.Collator('uk')

export function compareText(a: string, b: string): number {
  return collator.compare(a, b)
}

export function compareTitle(a: UserBook, b: UserBook): number {
  return compareText(a.book.title, b.book.title)
}

export function compareAuthor(a: UserBook, b: UserBook): number {
  const left = a.book.authors[0]
  const right = b.book.authors[0]
  // Books without an author go last.
  if (!left || !right) return (left ? 0 : 1) - (right ? 0 : 1)
  return compareText(left, right) || compareTitle(a, b)
}

export function compareRating(a: UserBook, b: UserBook): number {
  // Rated first, highest first; unrated keep their relative order.
  if (a.rating === null || b.rating === null) return (a.rating === null ? 1 : 0) - (b.rating === null ? 1 : 0)
  return b.rating - a.rating
}

export function compareFinished(a: UserBook, b: UserBook): number {
  if (!a.finished_at || !b.finished_at) return (a.finished_at ? 0 : 1) - (b.finished_at ? 0 : 1)
  return Date.parse(b.finished_at) - Date.parse(a.finished_at)
}

/** A sorted copy; `recent` keeps the server's order. Array.sort is stable, so ties keep it too. */
export function sortBooks(books: UserBook[], sort: LibrarySort): UserBook[] {
  const comparators: Partial<Record<LibrarySort, (a: UserBook, b: UserBook) => number>> = {
    title: compareTitle,
    author: compareAuthor,
    rating: compareRating,
    finished: compareFinished,
  }
  const compare = comparators[sort]
  return compare ? [...books].sort(compare) : books
}
