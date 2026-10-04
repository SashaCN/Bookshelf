import { describe, expect, it } from 'vitest'
import { bestDay, layoutBars, totalPages } from './chart'

const days = [
  { date: '2026-10-01', pages: 10 },
  { date: '2026-10-02', pages: 0 },
  { date: '2026-10-03', pages: 40 },
  { date: '2026-10-04', pages: 1 },
]

describe('layoutBars', () => {
  it('lets the tallest day fill the height and scales the others to it', () => {
    const bars = layoutBars(days, 100, 80)

    expect(bars[2]!.height).toBe(80)
    expect(bars[0]!.height).toBe(20)
    expect(bars[2]!.y).toBe(0)
    expect(bars[0]!.y).toBe(60)
  })

  it('gives a day without reading no bar and a day with reading a visible one', () => {
    const bars = layoutBars(days, 100, 80)

    expect(bars[1]!.height).toBe(0)
    expect(bars[3]!.height).toBe(3)
  })

  it('spreads the bars over the width with a gap between them', () => {
    const bars = layoutBars(days, 100, 80, 4)

    expect(bars[0]!.width).toBe(22)
    expect(bars.map((bar) => bar.x)).toEqual([0, 26, 52, 78])
    expect(bars[3]!.x + bars[3]!.width).toBe(100)
  })

  it('does not divide by zero when nothing was read', () => {
    const bars = layoutBars([{ date: '2026-10-01', pages: 0 }, { date: '2026-10-02', pages: 0 }], 100, 80)

    expect(bars.map((bar) => bar.height)).toEqual([0, 0])
  })

  it('has nothing to draw for no days', () => {
    expect(layoutBars([], 100, 80)).toEqual([])
  })

  it('keeps bars visible when there are more days than units of width', () => {
    const many = Array.from({ length: 400 }, (_, index) => ({ date: `d${index}`, pages: 5 }))

    expect(layoutBars(many, 100, 80).every((bar) => bar.width >= 1)).toBe(true)
  })
})

describe('bestDay', () => {
  it('finds the day with the most pages', () => {
    expect(bestDay(days)).toEqual({ date: '2026-10-03', pages: 40 })
  })

  it('takes the first of equal days', () => {
    expect(bestDay([{ date: 'a', pages: 5 }, { date: 'b', pages: 5 }])?.date).toBe('a')
  })

  it('is null when nothing was read', () => {
    expect(bestDay([{ date: 'a', pages: 0 }])).toBeNull()
    expect(bestDay([])).toBeNull()
  })
})

describe('totalPages', () => {
  it('adds the days up', () => {
    expect(totalPages(days)).toBe(51)
    expect(totalPages([])).toBe(0)
  })
})
