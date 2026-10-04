import { describe, expect, it } from 'vitest'
import { formatDay, signed } from './format'

describe('formatDay', () => {
  it('writes the day and the month in Ukrainian', () => {
    expect(formatDay('2026-10-05')).toBe('5 жовтня')
    expect(formatDay('2026-01-31')).toBe('31 січня')
  })

  it('does not shift the day with the time zone of the device', () => {
    expect(formatDay('2026-03-01')).toBe('1 березня')
  })
})

describe('signed', () => {
  it('marks gains, corrections and no change', () => {
    expect(signed(18)).toBe('+18')
    expect(signed(-270)).toBe('−270')
    expect(signed(0)).toBe('0')
  })
})
