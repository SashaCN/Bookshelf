import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { socialApi } from '@/api/social'
import { useAuthStore } from '@/stores/auth'
import { useLibraryStore } from '@/stores/library'
import { makeBook, makeQuote, makeUserBook } from '@/test/fixtures'
import { makeReview } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { UserBook } from '@/types/api'
import BookView from './BookView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
    progress: vi.fn(),
    reread: vi.fn(),
    logs: vi.fn(),
  },
  catalogApi: { search: vi.fn() },
}))

vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

vi.mock('@/api/social', () => ({
  socialApi: { bookReviews: vi.fn(), like: vi.fn(), unlike: vi.fn(), deletePost: vi.fn(), report: vi.fn() },
}))

async function mountView(userBook: UserBook) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue([userBook])
  vi.mocked(libraryApi.logs).mockResolvedValue({ data: [], links: { next: null } })
  await router.push({ name: 'book', params: { id: userBook.id } })

  const wrapper = mount(BookView, { props: { id: userBook.id }, global: { plugins } })
  await flushPromises()

  return { wrapper, router, library: useLibraryStore() }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('BookView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.mocked(quotesApi.list).mockResolvedValue({ data: [], links: { next: null } })
    vi.mocked(socialApi.bookReviews).mockResolvedValue({ data: [], links: { next: null }, meta: { my_review_id: null, reviews_count: 0, average_rating: null } })
  })

  describe('what readers write', () => {
    it('shows the reviews of the book of the catalog, not of the library entry', async () => {
      vi.mocked(socialApi.bookReviews).mockResolvedValue({
        data: [makeReview({ id: 2, content: 'A fine book' })],
        links: { next: null },
        meta: { my_review_id: null, reviews_count: 1, average_rating: 4 },
      })

      const { wrapper } = await mountView(makeUserBook({ id: 7, book: makeBook({ id: 31 }) }))

      expect(socialApi.bookReviews).toHaveBeenCalledWith(31, 1)
      expect(wrapper.text()).toContain('Що пишуть читачі')
      expect(wrapper.text()).toContain('A fine book')
    })

    it('offers to write a review, starting from the library entry', async () => {
      const { wrapper } = await mountView(makeUserBook({ id: 7, book: makeBook({ id: 31 }) }))

      const write = wrapper.findAll('a').find((link) => link.text() === 'Написати відгук')

      expect(write?.attributes('href')).toBe('/community/new?book=7')
    })

    it('does not get in the way of the book when the reviews cannot be loaded', async () => {
      vi.mocked(socialApi.bookReviews).mockRejectedValue(new ApiError(500, null))

      const { wrapper } = await mountView(makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'] }))

      expect(wrapper.text()).toContain('Не вдалося завантажити відгуки.')
      expect(wrapper.text()).toContain('Atomic Habits')
      expect(buttonWithText(wrapper, 'Прочитано').exists()).toBe(true)
    })
  })

  it('shows the book, its status and its progress', async () => {
    const { wrapper } = await mountView(
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 160, progress_percent: 50 }),
    )

    expect(wrapper.text()).toContain('Atomic Habits')
    expect(wrapper.text()).toContain('James Clear')
    expect(wrapper.text()).toContain('160 з 320 стор.')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('50')
  })

  it('has a block for reading the book in the app: an upload, or the way to the reader', async () => {
    const without = await mountView(makeUserBook({ has_text: false }))
    expect(without.wrapper.text()).toContain('Читати в застосунку')
    expect(buttonWithText(without.wrapper, 'Завантажити EPUB').exists()).toBe(true)

    const withText = await mountView(makeUserBook({ has_text: true, text_progress: 0.25 }))
    expect(withText.wrapper.find('a[href="/read/1"]').text()).toBe('Продовжити читати')
    expect(withText.wrapper.text()).toContain('Прочитано 25%')
  })

  it('offers exactly the actions the server allows', async () => {
    const { wrapper } = await mountView(makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'] }))

    expect(buttonWithText(wrapper, 'Прочитано').exists()).toBe(true)
    expect(buttonWithText(wrapper, 'Відкласти').exists()).toBe(true)
    expect(() => buttonWithText(wrapper, 'Почати читати')).toThrow()
  })

  it('moves to the chosen status', async () => {
    const { wrapper, library } = await mountView(makeUserBook())
    vi.mocked(libraryApi.update).mockResolvedValue(
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'] }),
    )

    await buttonWithText(wrapper, 'Почати читати').trigger('click')
    await flushPromises()

    expect(libraryApi.update).toHaveBeenCalledWith(1, { status: 'reading' })
    expect(library.find(1)?.status).toBe('reading')
    expect(wrapper.text()).toContain('Читаю')
  })

  it('moves the bookmark to the typed page and reloads the journal', async () => {
    const { wrapper, library } = await mountView(
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 100 }),
    )
    vi.mocked(libraryApi.progress).mockResolvedValue({
      data: makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 150, progress_percent: 46 }),
      meta: { pages: 50, reached_end: false, streak: 1 },
    })
    expect(libraryApi.logs).toHaveBeenCalledTimes(1)

    await wrapper.get('#current-page').setValue('150')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(libraryApi.progress).toHaveBeenCalledWith(1, 150)
    expect(library.find(1)?.current_page).toBe(150)
    expect(wrapper.text()).toContain('150 з 320 стор.')
    expect(libraryApi.logs).toHaveBeenCalledTimes(2)
  })

  it('starts reading a wanted book when its page is set, and says so', async () => {
    const { wrapper } = await mountView(makeUserBook())

    expect(wrapper.text()).toContain('Оновлення сторінки позначить книгу як «Читаю».')

    vi.mocked(libraryApi.progress).mockResolvedValue({
      data: makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 20 }),
      meta: { pages: 20, reached_end: false, streak: 1 },
    })
    await wrapper.get('#current-page').setValue('20')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Читаю')
    expect(wrapper.text()).not.toContain('Оновлення сторінки позначить книгу як «Читаю».')
  })

  describe('rereading', () => {
    const finished = (extra: Partial<UserBook> = {}) =>
      makeUserBook({ status: 'finished', allowed_statuses: ['reading'], current_page: 320, times_read: 1, ...extra })

    it('asks for confirmation, then restarts the book and shows the previous pass', async () => {
      const { wrapper, library } = await mountView(finished())
      vi.mocked(libraryApi.reread).mockResolvedValue(
        makeUserBook({
          status: 'reading',
          allowed_statuses: ['finished', 'abandoned'],
          times_read: 1,
          reads: [{ started_at: '2026-09-01T10:00:00+00:00', finished_at: '2026-09-20T10:00:00+00:00' }],
        }),
      )

      await buttonWithText(wrapper, 'Перечитати').trigger('click')
      expect(libraryApi.reread).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Прогрес повернеться на сторінку 0')

      await buttonWithText(wrapper, 'Почати знову').trigger('click')
      await flushPromises()

      expect(libraryApi.reread).toHaveBeenCalledWith(1)
      expect(library.find(1)?.status).toBe('reading')
      expect(wrapper.text()).toContain('Прочитано 1 раз')
      expect(wrapper.text()).toContain('Попередні прочитання')
      expect(wrapper.text()).toContain('1 вересня')
      expect(wrapper.text()).not.toContain('Перечитати')
    })

    it('lets the reader back out', async () => {
      const { wrapper } = await mountView(finished())

      await buttonWithText(wrapper, 'Перечитати').trigger('click')
      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(libraryApi.reread).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Перечитати')
    })

    it('is not offered for a book that is not finished', async () => {
      const { wrapper } = await mountView(makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'] }))

      expect(wrapper.text()).not.toContain('Перечитати')
    })

    it('says so when the server refuses', async () => {
      const { wrapper } = await mountView(finished())
      vi.mocked(libraryApi.reread).mockRejectedValue(new ApiError(422, { errors: { status: ['library.reread_not_finished'] } }))

      await buttonWithText(wrapper, 'Перечитати').trigger('click')
      await buttonWithText(wrapper, 'Почати знову').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Перечитувати можна лише прочитану книгу.')
    })

    it('fetches the earlier passes of the entry when the list only counted them', async () => {
      const entry = finished({ times_read: 2 })
      vi.mocked(libraryApi.get).mockResolvedValue({ ...entry, reads: [{ started_at: null, finished_at: '2026-01-10T10:00:00+00:00' }] })

      const { wrapper } = await mountView(entry)

      expect(libraryApi.get).toHaveBeenCalledWith(entry.id)
      expect(wrapper.text()).toContain('Попередні прочитання')
      expect(wrapper.text()).toContain('до 10 січня')
    })

    it('shows the passes in the reader time zone', async () => {
      const entry = finished({ times_read: 2, reads: [{ started_at: null, finished_at: '2026-10-05T22:30:00+00:00' }] })

      const { wrapper } = await mountView(entry)
      useAuthStore().user = { id: 1, name: 'Olena', email: 'olena@example.com', handle: 'olena', bio: null, timezone: 'Europe/Kyiv' } as never
      await flushPromises()

      // 22:30 UTC is already the next day in Kyiv, the zone of the signed-in reader.
      expect(wrapper.text()).toContain('до 6 жовтня')
    })

    it('shows the plural form of the times read', async () => {
      const { wrapper } = await mountView(
        finished({ times_read: 5, reads: [{ started_at: null, finished_at: '2026-01-10T10:00:00+00:00' }] }),
      )

      expect(wrapper.text()).toContain('Прочитано 5 разів')
      expect(wrapper.text()).toContain('до 10 січня')
    })
  })

  it('does not offer a page form for a finished book', async () => {
    const { wrapper } = await mountView(
      makeUserBook({ status: 'finished', allowed_statuses: ['reading'], current_page: 320 }),
    )

    expect(wrapper.find('#current-page').exists()).toBe(false)
  })

  it('does not offer a page form while the page count is unknown', async () => {
    const { wrapper } = await mountView(makeUserBook({ total_pages: null }))

    expect(wrapper.find('#current-page').exists()).toBe(false)
  })

  it('explains a rejected page in the reader\'s language', async () => {
    const { wrapper } = await mountView(
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 100 }),
    )
    vi.mocked(libraryApi.progress).mockRejectedValue(new ApiError(422, { errors: { page: ['library.page_above_total'] } }))
    vi.mocked(libraryApi.get).mockResolvedValue(
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 100 }),
    )

    await wrapper.get('#current-page').setValue('150')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('У книзі немає такої сторінки.')
  })

  it('shows the reading journal of a book that is being read', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockResolvedValue([
      makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 118 }),
    ])
    vi.mocked(libraryApi.logs).mockResolvedValue({
      data: [{ id: 2, from_page: 100, to_page: 118, pages: 18, logged_on: '2026-10-05', created_at: '2026-10-05T10:00:00+00:00' }],
      links: { next: null },
    })
    await router.push({ name: 'book', params: { id: 1 } })

    const wrapper = mount(BookView, { props: { id: 1 }, global: { plugins } })
    await flushPromises()

    expect(libraryApi.logs).toHaveBeenCalledWith(1, 1)
    expect(wrapper.text()).toContain('Історія читання')
    expect(wrapper.text()).toContain('5 жовтня')
    expect(wrapper.text()).toContain('+18 стор.')
  })

  it('has no journal for a book that is only wanted', async () => {
    const { wrapper } = await mountView(makeUserBook())

    expect(wrapper.text()).not.toContain('Історія читання')
    expect(libraryApi.logs).not.toHaveBeenCalled()
  })

  it('will not start reading a book with an unknown page count', async () => {
    const { wrapper } = await mountView(makeUserBook({ total_pages: null }))

    expect(buttonWithText(wrapper, 'Почати читати').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Щоб почати читати, вкажіть кількість сторінок.')
  })

  it('saves the page count and then allows starting', async () => {
    const { wrapper } = await mountView(makeUserBook({ total_pages: null }))
    vi.mocked(libraryApi.update).mockResolvedValue(makeUserBook({ total_pages: 250 }))

    await wrapper.get('#total-pages').setValue('250')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(libraryApi.update).toHaveBeenCalledWith(1, { total_pages: 250 })
    expect(buttonWithText(wrapper, 'Почати читати').attributes('disabled')).toBeUndefined()
  })

  it('explains a rejected change in the reader\'s language', async () => {
    const { wrapper } = await mountView(makeUserBook())
    vi.mocked(libraryApi.update).mockRejectedValue(
      new ApiError(422, { errors: { status: ['library.invalid_transition'] } }),
    )

    await buttonWithText(wrapper, 'Почати читати').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('З поточного статусу не можна перейти до цього.')
  })

  it('saves a rating and clears it when the same star is tapped again', async () => {
    const { wrapper } = await mountView(makeUserBook({ status: 'finished', allowed_statuses: ['reading'] }))
    vi.mocked(libraryApi.update)
      .mockResolvedValueOnce(makeUserBook({ status: 'finished', rating: 4 }))
      .mockResolvedValueOnce(makeUserBook({ status: 'finished', rating: null }))

    await wrapper.findAll('[aria-label="4 / 5"]')[0]!.trigger('click')
    await flushPromises()
    await wrapper.findAll('[aria-label="4 / 5"]')[0]!.trigger('click')
    await flushPromises()

    expect(libraryApi.update).toHaveBeenNthCalledWith(1, 1, { rating: 4 })
    expect(libraryApi.update).toHaveBeenNthCalledWith(2, 1, { rating: null })
  })

  it('asks for confirmation before removing, then returns to the library', async () => {
    const { wrapper, router } = await mountView(makeUserBook())
    vi.mocked(libraryApi.remove).mockResolvedValue(null)

    await buttonWithText(wrapper, 'Видалити з бібліотеки').trigger('click')
    expect(libraryApi.remove).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('Книга зникне з вашої бібліотеки')

    await buttonWithText(wrapper, 'Видалити').trigger('click')
    await flushPromises()

    expect(libraryApi.remove).toHaveBeenCalledWith(1)
    expect(router.currentRoute.value.name).toBe('library')
  })

  it('lets the reader back out of removing', async () => {
    const { wrapper } = await mountView(makeUserBook())

    await buttonWithText(wrapper, 'Видалити з бібліотеки').trigger('click')
    await buttonWithText(wrapper, 'Скасувати').trigger('click')

    expect(libraryApi.remove).not.toHaveBeenCalled()
    expect(wrapper.text()).not.toContain('Книга зникне з вашої бібліотеки')
  })

  describe('quotes of the book', () => {
    it('shows the newest three, with a way to see them all', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue({
        data: [5, 4, 3, 2, 1].map((id) => makeQuote({ id, content: `Quote ${id}`, user_book_id: 1 })),
        links: { next: '/api/quotes?book=1&page=2' },
      })
      const { wrapper } = await mountView(makeUserBook())

      const list = wrapper.findAll('blockquote').map((quote) => quote.text())

      expect(quotesApi.list).toHaveBeenCalledWith({ book: 1 })
      expect(list).toEqual(['Quote 5', 'Quote 4', 'Quote 3'])
      expect(wrapper.findAll('a').find((link) => link.text() === 'Усі цитати')?.attributes('href')).toBe('/quotes?book=1')
    })

    it('shows the page of a quote', async () => {
      vi.mocked(quotesApi.list).mockResolvedValue({ data: [makeQuote({ page: 42 })], links: { next: null } })
      const { wrapper } = await mountView(makeUserBook())

      expect(wrapper.text()).toContain('с. 42')
    })

    it('offers adding a quote for this book, whatever its status', async () => {
      const { wrapper } = await mountView(makeUserBook({ status: 'want' }))

      const add = wrapper.findAll('a').find((link) => link.text() === 'Додати цитату')

      expect(add?.attributes('href')).toBe('/quotes/new?book=1')
    })

    it('comes before the reading journal', async () => {
      const { wrapper } = await mountView(
        makeUserBook({ status: 'reading', allowed_statuses: ['finished', 'abandoned'], current_page: 100 }),
      )

      const text = wrapper.text()

      expect(text.indexOf('Цитати')).toBeGreaterThan(-1)
      expect(text.indexOf('Цитати')).toBeLessThan(text.indexOf('Історія читання'))
    })

    it('says there are none yet instead of showing an empty list', async () => {
      const { wrapper } = await mountView(makeUserBook())

      expect(wrapper.text()).toContain('У цій книзі ще немає цитат.')
      expect(wrapper.find('blockquote').exists()).toBe(false)
      expect(wrapper.findAll('a').some((link) => link.text() === 'Усі цитати')).toBe(false)
    })

    it('does not get in the way of the page when the quotes cannot be loaded, and can try again', async () => {
      vi.mocked(quotesApi.list)
        .mockRejectedValueOnce(new ApiError(500, null))
        .mockResolvedValueOnce({ data: [makeQuote({ content: 'Back again' })], links: { next: null } })
      const { wrapper } = await mountView(makeUserBook())

      expect(wrapper.text()).toContain('Atomic Habits')
      expect(wrapper.text()).toContain('Не вдалося завантажити цитати.')
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.findAll('a').some((link) => link.text() === 'Додати цитату')).toBe(true)

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.get('blockquote').text()).toBe('Back again')
      expect(wrapper.text()).not.toContain('Не вдалося завантажити цитати.')
    })
  })

  it('says so when the book does not exist', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockResolvedValue([])
    vi.mocked(libraryApi.get).mockRejectedValue(new ApiError(404, { message: 'Not found' }))
    await router.push({ name: 'book', params: { id: 99 } })

    const wrapper = mount(BookView, { props: { id: 99 }, global: { plugins } })
    await flushPromises()

    expect(wrapper.text()).toContain('Цієї книги немає у вашій бібліотеці.')
  })

  it('does not let a second rating tap through while the first one is being saved', async () => {
    const { wrapper } = await mountView(makeUserBook({ status: 'finished', allowed_statuses: ['reading'] }))
    let finish: (book: UserBook) => void = () => undefined
    vi.mocked(libraryApi.update).mockImplementationOnce(() => new Promise((resolve) => (finish = resolve)))

    await wrapper.findAll('[aria-label="4 / 5"]')[0]!.trigger('click')

    expect(wrapper.findAll('[aria-label="5 / 5"]')[0]!.attributes('disabled')).toBeDefined()

    finish(makeUserBook({ status: 'finished', rating: 4 }))
    await flushPromises()

    expect(wrapper.findAll('[aria-label="5 / 5"]')[0]!.attributes('disabled')).toBeUndefined()
  })

  it('stops showing the previous book while the next one loads, and ignores a late answer for it', async () => {
    const { wrapper } = await mountView(makeUserBook({ id: 1, status: 'finished', allowed_statuses: [] }))
    let late: (book: UserBook) => void = () => undefined
    vi.mocked(libraryApi.get).mockImplementationOnce(() => new Promise((resolve) => (late = resolve)))

    await wrapper.setProps({ id: 2 })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Atomic Habits')
    expect(wrapper.text()).toContain('Завантаження…')

    late(makeUserBook({ id: 2, book: makeBook({ title: 'Deep Work' }), status: 'finished', allowed_statuses: [] }))
    await flushPromises()

    expect(wrapper.text()).toContain('Deep Work')
  })
})
