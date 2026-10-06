import type { UserBook } from './api'

/**
 * Every offset in the reader (`start` and `end` of a quote, `characters` of a chapter) counts UTF-16 code units of
 * the text of a chapter: the unit of a JavaScript string index, so an emoji is two. The server counts the same way.
 */

/** One line of the table of contents. */
export interface ChapterSummary {
  position: number
  title: string | null
  characters: number
}

/** Where the reader is: a chapter, and how far into it (0 to 1). */
export interface TextPosition {
  chapter: number
  chapter_progress: number
  updated_at: string | null
}

/** The text of a book the reader uploaded. */
export interface BookText {
  id: number
  user_book_id: number
  source_name: string
  chapters_count: number
  characters_count: number
  position: TextPosition
  /** How far into the whole text, from 0 to 1. */
  progress: number
  chapters: ChapterSummary[]
}

/** A quote selected from a chapter, to be highlighted in it. */
export interface Highlight {
  /** The id of the quote. */
  id: number
  start: number
  end: number
  content: string
  note: string | null
}

export interface Chapter {
  position: number
  title: string | null
  /** Sanitized by the server: elements from a short list, no attributes, no scripts. */
  html: string
  characters: number
  highlights: Highlight[]
}

/** Where in the text of a book a saved quote comes from. */
export interface QuoteAnchor {
  book_text_id: number
  chapter: number
  start: number
  end: number
}

/** What is sent to say where a quote was selected. */
export interface AnchorPayload {
  chapter: number
  start: number
  end: number
}

export interface PositionPayload {
  chapter: number
  chapter_progress: number
}

export interface PositionResult {
  data: Omit<BookText, 'chapters'>
  /** The library entry as it is now: its page moves on with the reader. */
  meta: { user_book: UserBook }
}

export type ReaderTheme = 'auto' | 'light' | 'sepia' | 'dark'
export type ReaderFont = 'serif' | 'sans'

export interface ReaderSettings {
  /** In pixels. */
  fontSize: number
  /** A multiple of the font size. */
  lineHeight: number
  font: ReaderFont
  theme: ReaderTheme
}
