import { describe, expect, it } from 'vitest'
import { formatDay, formatNumber, formatShortDay, signed } from './format'

describe('formatDay', () => {
  it('writes the day and the month in Ukrainian', () => {
    expect(formatDay('2026-10-05')).toBe('5 жовтня')
    expect(formatDay('2026-01-31')).toBe('31 січня')
  })

  it('does not shift the day with the time zone of the device', () => {
    expect(formatDay('2026-03-01')).toBe('1 березня')
  })
})

describe('formatShortDay', () => {
  it('abbreviates the month', () => {
    expect(formatShortDay('2026-10-05')).toBe('5 жовт.')
    expect(formatShortDay('2026-03-01')).toBe('1 бер.')
  })
})

describe('signed', () => {
  it('marks gains, corrections and no change', () => {
    expect(signed(18)).toBe('+18')
    expect(signed(-270)).toBe('−270')
    expect(signed(0)).toBe('0')
  })
})

describe('formatNumber', () => {
  it('uses a decimal comma and at most one decimal', () => {
    expect(formatNumber(13.4)).toBe('13,4')
    expect(formatNumber(16.6667)).toBe('16,7')
    expect(formatNumber(12)).toBe('12')
    expect(formatNumber(0)).toBe('0')
  })

  it('groups thousands without letting the number break across lines', () => {
    expect(formatNumber(3100)).toMatch(/^3\u00a0100$/)
    expect(formatNumber(999)).toBe('999')
  })
})
