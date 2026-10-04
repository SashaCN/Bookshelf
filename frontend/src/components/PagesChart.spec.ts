import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { createTestEnvironment } from '@/test/utils'
import type { DailyPages } from '@/types/api'
import PagesChart from './PagesChart.vue'

const days: DailyPages[] = [
  { date: '2026-10-01', pages: 10 },
  { date: '2026-10-02', pages: 0 },
  { date: '2026-10-03', pages: 40 },
]

function mountChart(data: DailyPages[]) {
  const { plugins } = createTestEnvironment()
  return mount(PagesChart, { props: { days: data }, global: { plugins } })
}

describe('PagesChart', () => {
  it('draws one bar per day, with the days without reading marked thinly', () => {
    const wrapper = mountChart(days)
    const bars = wrapper.findAll('rect')

    expect(bars).toHaveLength(3)
    expect(Number(bars[2]!.attributes('height'))).toBe(100)
    expect(Number(bars[1]!.attributes('height'))).toBeLessThan(3)
    expect(bars[1]!.classes()).toContain('fill-sunken')
  })

  it('highlights today, the last day', () => {
    const bars = mountChart(days).findAll('rect')

    expect(bars[2]!.classes()).toContain('fill-accent')
    expect(bars[0]!.classes()).not.toContain('fill-accent')
  })

  it('describes the chart for screen readers and on every bar', () => {
    const wrapper = mountChart(days)

    expect(wrapper.get('svg').attributes('aria-label')).toBe('Графік сторінок за 3 дні: 50 стор.')
    expect(wrapper.findAll('title')[2]!.text()).toBe('3 жовтня: 40 стор.')
  })

  it('sums up the period in words', () => {
    const text = mountChart(days).text()

    expect(text).toContain('50 стор.')
    expect(text).toContain('16,7 стор./день')
    expect(text).toContain('3 жовтня, 40 стор.')
  })

  it('labels the first and the last day of the axis', () => {
    const text = mountChart(days).get('.justify-between').text()

    expect(text).toContain('1 жовт.')
    expect(text).toContain('3 жовт.')
  })

  it('does not name a best day when nothing was read', () => {
    const wrapper = mountChart([{ date: '2026-10-01', pages: 0 }, { date: '2026-10-02', pages: 0 }])

    expect(wrapper.text()).not.toContain('Найкращий день')
    expect(wrapper.text()).toContain('0 стор.')
  })

  it('copes with no days at all', () => {
    const wrapper = mountChart([])

    expect(wrapper.findAll('rect')).toHaveLength(0)
    expect(wrapper.find('.justify-between').exists()).toBe(false)
  })
})
