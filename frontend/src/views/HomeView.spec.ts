import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { readerApi } from '@/api/reader'
import { makeReaderDoc } from '@/test/readerFixtures'
import { makeBook, makeQuote, makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Quote, ReaderDocument, UserBook } from '@/types/api'
import HomeView from './HomeView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))
vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

vi.mock('@/api/reader', () => ({
  readerApi: { list: vi.fn(), get: vi.fn(), upload: vi.fn(), remove: vi.fn(), savePosition: vi.fn(), chapter: vi.fn() },
}))

const reading = (id: number, title: string) =>
  makeUserBook({
    id,
    status: 'reading',
    allowed_statuses: ['finished', 'abandoned'],
    current_page: 100,
    progress_percent: 31,
    book: makeBook({ id, title }),
  })

async function mountView(items: UserBook[], daily: Quote | null | Error, documents: ReaderDocument[] | Error = []) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue(items)
  if (daily instanceof Error) {
    vi.mocked(quotesApi.daily).mockRejectedValue(daily)
  } else {
    vi.mocked(quotesApi.daily).mockResolvedValue(daily)
  }
  if (documents instanceof Error) {
    vi.mocked(readerApi.list).mockRejectedValue(documents)
  } else {
    vi.mocked(readerApi.list).mockResolvedValue(documents)
  }
  await router.push({ name: 'home' })

  const wrapper = mount(HomeView, { global: { plugins } })
  await flushPromises()

  return { wrapper }
}

describe('HomeView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('links to the feed', async () => {
    const { wrapper } = await mountView([reading(1, 'Дюна')], null)

    expect(wrapper.get('a[href="/feed"]').text()).toContain('Стрічка друзів')
    expect(wrapper.get('a[href="/feed"]').text()).toContain('Цитати людей, на яких ви підписані')
  })

  it('shows the feed and the reader as two matching cards', async () => {
    const { wrapper } = await mountView([reading(1, 'Дюна')], null)

    const feed = wrapper.get('a[href="/feed"]')
    const reader = wrapper.get('a[href="/reader"]')
    expect(reader.text()).toContain('Читалка EPUB')
    expect(reader.text()).toContain('Читайте EPUB прямо в застосунку')
    expect(reader.classes()).toEqual(feed.classes())
  })

  it('shows a card for each book being read', async () => {
    const { wrapper } = await mountView([reading(1, 'First Book'), reading(2, 'Second Book'), makeUserBook({ id: 3 })], null)

    expect(wrapper.text()).toContain('First Book')
    expect(wrapper.text()).toContain('Second Book')
    expect(wrapper.findAll('article')).toHaveLength(2)
  })

  it('invites the reader to the library when nothing is being read', async () => {
    const { wrapper } = await mountView([makeUserBook()], null)

    expect(wrapper.text()).toContain('Зараз нічого не читаєте')
    expect(wrapper.findAll('a').some((link) => link.attributes('href') === '/library')).toBe(true)
  })

  it('offers a retry when the library cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce([reading(1, 'First Book')])
    vi.mocked(quotesApi.daily).mockResolvedValue(null)
    vi.mocked(readerApi.list).mockResolvedValue([])
    await router.push({ name: 'home' })
    const wrapper = mount(HomeView, { global: { plugins } })
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('First Book')
  })

  describe('continue reading', () => {
    const read = makeReaderDoc({
      id: 7,
      title: 'Кобзар',
      author: 'Тарас Шевченко',
      chapters_count: 12,
      position: { chapter: 3, fraction: 0.5 },
      progress: 29.2,
      last_read_at: '2026-11-20T10:00:00Z',
    })

    it('shows the last read book with its progress, above the feed and reader cards', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], null, [makeReaderDoc({ id: 8, title: 'Нова' }), read])

      const card = wrapper.get('a[href="/reader/7"]')
      expect(card.text()).toContain('Продовжити читання')
      expect(card.text()).toContain('Кобзар')
      expect(card.text()).toContain('Тарас Шевченко')
      expect(card.text()).toContain('29%')
      expect(card.text()).toContain('Розділ 4 з 12')
      expect(card.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('29.2')
      expect(wrapper.text()).not.toContain('Нова')
      const html = wrapper.html()
      expect(html.indexOf('Продовжити читання')).toBeLessThan(html.indexOf('Стрічка друзів'))
    })

    it('replaces the library nudge when no library book is being read', async () => {
      const { wrapper } = await mountView([], null, [read])

      expect(wrapper.text()).toContain('Продовжити читання')
      expect(wrapper.text()).not.toContain('Зараз нічого не читаєте')
    })

    it('is hidden when no book has been read yet', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], null, [makeReaderDoc()])

      expect(wrapper.text()).not.toContain('Продовжити читання')
    })

    it('is hidden when there are no books', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], null, [])

      expect(wrapper.text()).not.toContain('Продовжити читання')
    })

    it('fails silently and leaves the rest of the screen alone', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], null, new ApiError(500, null))

      expect(wrapper.text()).toContain('First Book')
      expect(wrapper.text()).not.toContain('Продовжити читання')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })
  })

  describe('the quote of the day', () => {
    const daily = makeQuote({
      id: 6,
      content: 'You fall to the level of your systems.',
      page: 27,
      user_book_id: 4,
    })

    it('appears below the books being read', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], daily)

      const html = wrapper.html()
      expect(wrapper.text()).toContain('Цитата дня')
      expect(wrapper.get('section blockquote').text()).toBe('You fall to the level of your systems.')
      expect(wrapper.text()).toContain('Atomic Habits · James Clear · с. 27')
      expect(html.indexOf('First Book')).toBeLessThan(html.indexOf('Цитата дня'))
      expect(quotesApi.daily).toHaveBeenCalledTimes(1)
    })

    it('also appears when nothing is being read', async () => {
      const { wrapper } = await mountView([], daily)

      expect(wrapper.text()).toContain('Зараз нічого не читаєте')
      expect(wrapper.text()).toContain('Цитата дня')
    })

    it('links to its book and to sharing it', async () => {
      const { wrapper } = await mountView([], daily)

      const hrefs = Object.fromEntries(wrapper.findAll('section a').map((link) => [link.text(), link.attributes('href')]))

      expect(hrefs['Atomic Habits']).toBe('/library/4')
      expect(hrefs['Поділитися']).toBe('/quotes/6/share')
    })

    it('is hidden when the reader has no quotes', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], null)

      expect(wrapper.text()).not.toContain('Цитата дня')
      expect(wrapper.find('blockquote').exists()).toBe(false)
    })

    it('does not break the screen when it cannot be loaded', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], new ApiError(500, null))

      expect(wrapper.text()).toContain('First Book')
      expect(wrapper.text()).not.toContain('Цитата дня')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })

    it('does not break the screen when the request fails in an unexpected way', async () => {
      const { wrapper } = await mountView([reading(1, 'First Book')], new TypeError('Failed to fetch'))

      expect(wrapper.text()).toContain('First Book')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })
  })
})
