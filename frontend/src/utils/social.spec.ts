import { describe, expect, it } from 'vitest'
import { formatPostDate, initialOf } from './social'

describe('formatPostDate', () => {
  const now = new Date(2026, 9, 6, 12, 0, 0)

  it('leaves the year out for a post of this year', () => {
    expect(formatPostDate(new Date(2026, 9, 5, 10, 0, 0).toISOString(), now)).toBe('5 жовтня')
  })

  it('names the year of an older post', () => {
    expect(formatPostDate(new Date(2025, 11, 31, 10, 0, 0).toISOString(), now)).toBe('31 грудня 2025 р.')
  })

  it('gives nothing for a date it cannot read', () => {
    expect(formatPostDate('not a date', now)).toBe('')
  })
})

describe('initialOf', () => {
  it('is the first letter, in capitals', () => {
    expect(initialOf('  олена')).toBe('О')
  })

  it('is a question mark for an empty name', () => {
    expect(initialOf('   ')).toBe('?')
  })
})
