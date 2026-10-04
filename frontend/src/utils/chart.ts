import type { DailyPages } from '@/types/api'

export interface Bar {
  date: string
  pages: number
  x: number
  y: number
  width: number
  height: number
}

/** The shortest bar that is still visible, so a day with a few pages does not vanish next to a big one. */
const MIN_BAR_HEIGHT = 3

/**
 * Lays the days out as bars of a chart `width` by `height` units. The tallest day fills the height; a day without
 * reading gets no bar at all (the axis shows it), and a day with reading never gets a bar too small to see.
 */
export function layoutBars(days: DailyPages[], width: number, height: number, gap = 2): Bar[] {
  if (days.length === 0) {
    return []
  }

  const barWidth = Math.max(1, (width - gap * (days.length - 1)) / days.length)
  const tallest = Math.max(1, ...days.map((day) => day.pages))

  return days.map((day, index) => {
    const barHeight = day.pages === 0 ? 0 : Math.max(MIN_BAR_HEIGHT, (day.pages / tallest) * height)

    return {
      date: day.date,
      pages: day.pages,
      x: index * (barWidth + gap),
      y: height - barHeight,
      width: barWidth,
      height: barHeight,
    }
  })
}

/** The day with the most pages, or null when nothing was read. The first such day wins a tie. */
export function bestDay(days: DailyPages[]): DailyPages | null {
  let best: DailyPages | null = null

  for (const day of days) {
    if (day.pages > 0 && (best === null || day.pages > best.pages)) {
      best = day
    }
  }

  return best
}

export function totalPages(days: DailyPages[]): number {
  return days.reduce((sum, day) => sum + day.pages, 0)
}
