import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { statsApi } from '@/api/stats'
import { createTestEnvironment } from '@/test/utils'
import type { YearRecap, YearRecapResponse } from '@/types/api'
import YearRecapView from './YearRecapView.vue'

vi.mock('@/api/stats', () => ({ statsApi: { year: vi.fn() } }))

function makeRecap(overrides: Partial<YearRecap> = {}, meta = { first_year: 2024, current_year: 2026 }): YearRecapResponse {
  return {
    data: {
      year: 2025,
      books_finished: 5,
      pages_read: 1840,
      days_with_reading: 90,
      longest_streak_in_year: 12,
      chapters_read: 0,
      quotes_added: 7,
      reviews_written: 2,
      average_rating: 4.2,
      top_rated: [
        { title: 'Дюна', authors: ['Френк Герберт'], rating: 5, finished_at: '2025-03-01' },
        { title: 'Тіні забутих предків', authors: ['Михайло Коцюбинський'], rating: 4, finished_at: '2025-05-20' },
      ],
      longest_book: { title: 'Дюна', authors: ['Френк Герберт'], total_pages: 1000 },
      busiest_month: { month: 5, pages: 600 },
      goal: { target: 4, achieved: 5 },
      finished_books: [
        { title: 'Дюна', authors: ['Френк Герберт'], finished_at: '2025-03-01', rating: 5 },
        { title: 'Маленький принц', authors: [], finished_at: '2025-04-02', rating: null },
      ],
      ...overrides,
    },
    meta,
  }
}

async function mountView(year = 2025) {
  const { plugins, router } = createTestEnvironment()
  await router.push(`/stats/year/${year}`)
  const wrapper = mount(YearRecapView, { props: { year }, global: { plugins } })
  await flushPromises()
  return wrapper
}

describe('YearRecapView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(statsApi.year).mockResolvedValue(makeRecap())
  })

  it('shows the headline numbers', async () => {
    const wrapper = await mountView()

    expect(statsApi.year).toHaveBeenCalledWith(2025)
    expect(wrapper.get('h1').text()).toBe('Рік у книгах 2025')
    expect(wrapper.text()).toContain('Прочитано книг')
    expect(wrapper.text()).not.toContain('5 книг')
    expect(wrapper.text()).toContain('Прочитано сторінок')
    expect(wrapper.text()).toContain('Найдовша серія: 12 днів')
    expect(wrapper.text()).toContain('4,2')
    expect(wrapper.text()).not.toContain('Розділів у Читанці')
  })

  it('shows the chapters only when there are some', async () => {
    vi.mocked(statsApi.year).mockResolvedValue(makeRecap({ chapters_read: 21 }))
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Розділів у Читанці')
  })

  it('shows the goal result', async () => {
    expect((await mountView()).text()).toContain('Виконано: 5 із 4')

    vi.mocked(statsApi.year).mockResolvedValue(makeRecap({ goal: { target: 12, achieved: 5 } }))
    expect((await mountView()).text()).toContain('Прочитано 5 із 12')

    vi.mocked(statsApi.year).mockResolvedValue(makeRecap({ goal: null }))
    expect((await mountView()).text()).not.toContain('Річна ціль')
  })

  it('shows the top rated books with read-only stars', async () => {
    const wrapper = await mountView()

    const items = wrapper.findAll('ol li')
    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('Дюна')
    expect(items[0]!.get('[role="group"]').attributes('aria-label')).toBe('Оцінка 5 з 5')
    expect(items[0]!.find('button').exists()).toBe(false)
  })

  it('names the busiest month in the nominative and the longest book', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('травень')
    expect(wrapper.text()).not.toContain('травня')
    expect(wrapper.text()).toContain('1 000 стор.'.replace(' ', ' '))
    expect(wrapper.text()).toContain('Найдовша книга')
  })

  it('lists the finished books by date', async () => {
    const wrapper = await mountView()

    const items = wrapper.findAll('ul li')
    expect(items).toHaveLength(2)
    expect(items[0]!.text()).toContain('1 березня')
    expect(items[1]!.text()).toContain('Маленький принц')
    expect(items[1]!.text()).toContain('2 квітня')
  })

  it('has a heading for every section', async () => {
    const wrapper = await mountView()

    expect(wrapper.findAll('h2').map((h) => h.text())).toEqual([
      'Підсумки року',
      'Річна ціль',
      'Найвище оцінені',
      'Найдовша книга',
      'Найактивніший місяць',
      'Прочитані книги',
    ])
  })

  it('shows an empty state for a year without anything', async () => {
    vi.mocked(statsApi.year).mockResolvedValue(
      makeRecap({ books_finished: 0, pages_read: 0, quotes_added: 0, average_rating: null, top_rated: [], longest_book: null, busiest_month: null, goal: null, finished_books: [] }),
    )
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('У 2025 році тут порожньо')
    expect(wrapper.find('h2').exists()).toBe(false)
  })

  it('links to the neighbouring years within the years of activity', async () => {
    const wrapper = await mountView()
    const hrefs = wrapper.findAll('nav a').map((link) => link.attributes('href'))

    expect(hrefs).toEqual(['/stats/year/2024', '/stats/year/2026'])
  })

  it('has no link before the first year or after the current one', async () => {
    vi.mocked(statsApi.year).mockResolvedValue(makeRecap({ year: 2026 }, { first_year: 2026, current_year: 2026 }))
    const wrapper = await mountView(2026)

    expect(wrapper.findAll('nav a')).toHaveLength(0)
  })

  it('says so when the year does not exist', async () => {
    vi.mocked(statsApi.year).mockRejectedValue(new ApiError(404, null))
    const wrapper = await mountView(2010)

    expect(wrapper.text()).toContain('Для цього року немає підсумків')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('offers a retry when the recap cannot be loaded, and loads another year on navigation', async () => {
    vi.mocked(statsApi.year).mockRejectedValueOnce(new ApiError(500, null))
    const wrapper = await mountView()
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)

    vi.mocked(statsApi.year).mockResolvedValue(makeRecap({ year: 2024 }))
    await wrapper.setProps({ year: 2024 })
    await flushPromises()
    expect(statsApi.year).toHaveBeenLastCalledWith(2024)
    expect(wrapper.get('h1').text()).toBe('Рік у книгах 2024')
  })
})
