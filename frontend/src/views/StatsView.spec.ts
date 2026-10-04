import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { statsApi } from '@/api/stats'
import { createTestEnvironment } from '@/test/utils'
import { formatNumber } from '@/utils/format'
import type { GoalProgress, StatsSummary } from '@/types/api'
import StatsView from './StatsView.vue'

vi.mock('@/api/stats', () => ({
  statsApi: { summary: vi.fn(), daily: vi.fn(), goal: vi.fn(), setGoal: vi.fn(), removeGoal: vi.fn() },
}))

function makeSummary(overrides: Partial<StatsSummary> = {}): StatsSummary {
  return {
    today: { date: '2026-10-15', pages: 18 },
    streak: { current: 5, longest: 9, read_today: true },
    pace: 12.4,
    month: { pages: 210, books_finished: 1 },
    year: { pages: 3100, books_finished: 8 },
    forecasts: [],
    ...overrides,
  }
}

function makeGoal(overrides: Partial<GoalProgress> = {}): GoalProgress {
  return { year: 2026, target_books: 12, finished_books: 8, expected_books: 9.7, due_books: 9, remaining_books: 4, on_track: false, ...overrides }
}

/** `count` consecutive real days ending on 2026-10-15, with 0, 1, 2... pages. */
function makeDays(count: number) {
  return Array.from({ length: count }, (_, index) => ({
    date: new Date(Date.UTC(2026, 9, 15 - (count - 1) + index)).toISOString().slice(0, 10),
    pages: index,
  }))
}

async function mountView() {
  const { plugins, router } = createTestEnvironment()
  await router.push('/')
  const wrapper = mount(StatsView, { global: { plugins } })
  await flushPromises()
  return wrapper
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('StatsView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(statsApi.summary).mockResolvedValue(makeSummary())
    vi.mocked(statsApi.goal).mockResolvedValue(makeGoal())
    vi.mocked(statsApi.daily).mockResolvedValue({ data: makeDays(30), meta: { from: '2026-10-01', to: '2026-10-30', total_pages: 435 } })
  })

  it('shows the streak, the pace and the totals', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('5 днів')
    expect(wrapper.text()).toContain('Найдовша: 9 днів')
    expect(wrapper.text()).toContain('12,4')
    expect(wrapper.text()).toContain('стор./день')
    expect(wrapper.text()).toContain('210 стор.')
    expect(wrapper.text()).toContain('1 книга')
    expect(wrapper.text()).toContain(`${formatNumber(3100)} стор.`)
    expect(wrapper.text()).toContain('8 книг')
  })

  it('nudges the reader to keep a streak alive that has not been read today', async () => {
    vi.mocked(statsApi.summary).mockResolvedValue(makeSummary({ streak: { current: 3, longest: 3, read_today: false } }))

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Прочитайте сьогодні хоч сторінку')
  })

  it('takes the year of the goal from the reader\'s own calendar', async () => {
    await mountView()

    expect(statsApi.goal).toHaveBeenCalledWith(2026)
  })

  it('shows the goal and how the reader stands', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Ціль на 2026 рік')
    expect(wrapper.text()).toContain('Відстаєте від графіка')
  })

  it('draws the chart of the last 30 days and switches the period', async () => {
    const wrapper = await mountView()
    expect(statsApi.daily).toHaveBeenCalledWith(30)
    expect(wrapper.findAll('rect')).toHaveLength(30)

    vi.mocked(statsApi.daily).mockResolvedValue({ data: makeDays(7), meta: { from: '2026-10-09', to: '2026-10-15', total_pages: 21 } })
    await buttonWithText(wrapper, '7').trigger('click')
    await flushPromises()

    expect(statsApi.daily).toHaveBeenLastCalledWith(7)
    expect(wrapper.findAll('rect')).toHaveLength(7)
    expect(buttonWithText(wrapper, '7').attributes('aria-pressed')).toBe('true')
    expect(buttonWithText(wrapper, '30').attributes('aria-pressed')).toBe('false')
  })

  it('ignores a slow answer for a period the reader has already left', async () => {
    const wrapper = await mountView()
    let answerSlow!: (value: Awaited<ReturnType<typeof statsApi.daily>>) => void
    vi.mocked(statsApi.daily)
      .mockReturnValueOnce(new Promise((resolve) => (answerSlow = resolve)))
      .mockResolvedValueOnce({ data: makeDays(90), meta: { from: '', to: '', total_pages: 0 } })

    void buttonWithText(wrapper, '7').trigger('click')
    await buttonWithText(wrapper, '90').trigger('click')
    await flushPromises()
    answerSlow({ data: makeDays(7), meta: { from: '', to: '', total_pages: 0 } })
    await flushPromises()

    expect(wrapper.findAll('rect')).toHaveLength(90)
  })

  it('keeps the rest of the screen when only the chart fails, and can retry it', async () => {
    const wrapper = await mountView()
    vi.mocked(statsApi.daily).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce({
      data: makeDays(7),
      meta: { from: '', to: '', total_pages: 0 },
    })

    await buttonWithText(wrapper, '7').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
    expect(wrapper.text()).toContain('Ціль на 2026 рік')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.findAll('rect')).toHaveLength(7)
  })

  it('lists when each book will be finished', async () => {
    vi.mocked(statsApi.summary).mockResolvedValue(
      makeSummary({
        forecasts: [
          { user_book_id: 3, title: 'Atomic Habits', current_page: 100, total_pages: 320, pages_per_day: 20, days_left: 11, finish_on: '2026-10-26' },
          { user_book_id: 4, title: 'Dune', current_page: 0, total_pages: 600, pages_per_day: null, days_left: null, finish_on: null },
          { user_book_id: 5, title: 'Ulysses', current_page: 700, total_pages: 700, pages_per_day: 3, days_left: 0, finish_on: '2026-10-15' },
        ],
      }),
    )

    const wrapper = await mountView()
    const items = wrapper.findAll('li')

    expect(items).toHaveLength(3)
    expect(items[0]!.text()).toContain('Atomic Habits')
    expect(items[0]!.text()).toContain('Залишилось 220 стор.')
    expect(items[0]!.text()).toContain('≈ 26 жовтня (через 11 днів)')
    expect(items[0]!.get('a').attributes('href')).toBe('/library/3')
    expect(items[1]!.text()).toContain('Поки замало даних для прогнозу')
    expect(items[2]!.text()).toContain('Ви на останній сторінці')
    expect(items[2]!.text()).not.toContain('Залишилось')
  })

  it('says so when there is nothing to forecast', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Немає книг у читанні')
  })

  it('saves a goal for the year and shows the new standing', async () => {
    vi.mocked(statsApi.goal).mockResolvedValue(makeGoal({ target_books: null, expected_books: null, due_books: null, remaining_books: null, on_track: null }))
    vi.mocked(statsApi.setGoal).mockResolvedValue(makeGoal({ target_books: 24, on_track: true, remaining_books: 16 }))
    const wrapper = await mountView()

    await wrapper.get('#goal-target').setValue('24')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(statsApi.setGoal).toHaveBeenCalledWith(2026, 24)
    expect(wrapper.text()).toContain('ціль 24')
    expect(wrapper.text()).toContain('Ви в графіку')
  })

  it('explains a goal that could not be saved and keeps the form', async () => {
    vi.mocked(statsApi.goal).mockResolvedValue(makeGoal({ target_books: null, expected_books: null, due_books: null, remaining_books: null, on_track: null }))
    vi.mocked(statsApi.setGoal).mockRejectedValue(new ApiError(500, null))
    const wrapper = await mountView()

    await wrapper.get('#goal-target').setValue('24')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
    expect((wrapper.get('#goal-target').element as HTMLInputElement).value).toBe('24')
  })

  it('removes the goal and asks for a new one', async () => {
    vi.mocked(statsApi.removeGoal).mockResolvedValue(null)
    const wrapper = await mountView()
    vi.mocked(statsApi.goal).mockResolvedValue(makeGoal({ target_books: null, expected_books: null, due_books: null, remaining_books: null, on_track: null }))

    await buttonWithText(wrapper, 'Прибрати').trigger('click')
    await flushPromises()

    expect(statsApi.removeGoal).toHaveBeenCalledWith(2026)
    expect(wrapper.text()).toContain('Поставте ціль')
  })

  it('offers a retry when the statistics cannot be loaded', async () => {
    vi.mocked(statsApi.summary).mockRejectedValueOnce(new ApiError(500, null))
    const wrapper = await mountView()
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Ціль на 2026 рік')
  })
})
