/** What `POST /api/library/import` reports about a Goodreads CSV. Row numbers count like a spreadsheet (the header is row 1). */
export interface GoodreadsImportResult {
  imported: number
  skipped_existing: number
  skipped_invalid: number
  /** The first 20 unusable rows. */
  errors: { row: number; reason: string }[]
}
