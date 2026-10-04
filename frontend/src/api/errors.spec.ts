import { describe, expect, it } from 'vitest'
import { apiErrorCode, isApiErrorCode } from './errors'
import { ApiError } from './http'

describe('apiErrorCode', () => {
  it('reads the code from the response message', () => {
    expect(apiErrorCode(new ApiError(503, { message: 'catalog.unavailable' }))).toBe('catalog.unavailable')
  })

  it('reads the code from a validation error', () => {
    const error = new ApiError(422, {
      message: 'The given data was invalid.',
      errors: { total_pages: ['library.total_pages_required'] },
    })

    expect(apiErrorCode(error)).toBe('library.total_pages_required')
  })

  it('recognises the codes of quotes', () => {
    const error = new ApiError(422, {
      message: 'The given data was invalid.',
      errors: { page: ['quotes.page_above_total'] },
    })

    expect(apiErrorCode(error)).toBe('quotes.page_above_total')
  })

  it('does not take any dotted word for a code', () => {
    const error = new ApiError(422, { message: 'Invalid', errors: { page: ['other.page_above_total', 'quotes.Bad'] } })

    expect(apiErrorCode(error)).toBeNull()
    expect(isApiErrorCode('goals.something_wrong')).toBe(true)
    expect(isApiErrorCode('The page field is required.')).toBe(false)
    expect(isApiErrorCode(42)).toBe(false)
  })

  it('ignores ordinary validation messages', () => {
    const error = new ApiError(422, { message: 'Invalid', errors: { title: ['The title field is required.'] } })

    expect(apiErrorCode(error)).toBeNull()
  })

  it('returns null for anything that is not an ApiError', () => {
    expect(apiErrorCode(new Error('network'))).toBeNull()
    expect(apiErrorCode(null)).toBeNull()
  })
})
