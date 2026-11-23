import { describe, expect, it } from 'vitest'
import { isValidIsbn13 } from './isbn'

describe('isValidIsbn13', () => {
  it('accepts valid ISBN-13 with 978 and 979 prefixes', () => {
    expect(isValidIsbn13('9780306406157')).toBe(true)
    expect(isValidIsbn13('9791034312122')).toBe(true)
  })

  it('rejects a wrong check digit, wrong prefix, wrong length and non-digits', () => {
    expect(isValidIsbn13('9780306406158')).toBe(false)
    expect(isValidIsbn13('9770306406159')).toBe(false)
    expect(isValidIsbn13('978030640615')).toBe(false)
    expect(isValidIsbn13('978030640615x')).toBe(false)
    expect(isValidIsbn13('')).toBe(false)
  })
})
