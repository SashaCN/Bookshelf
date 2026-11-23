import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { useAuthStore } from '@/stores/auth'
import { useLibraryStore } from '@/stores/library'
import { makeBook, makeQuote, makeUserBook } from '@/test/fixtures'
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
    logs: vi.fn(),
  },
  catalogApi: { search: vi.fn() },
}))

vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
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
  })

  it('offers to read the linked EPUB', async () => {
    const entry = makeUserBook({ id: 3 })
    vi.mocked(libraryApi.get).mockResolvedValue({ ...entry, reader_document_id: 9 })
    const { wrapper } = await mountView(entry)

    const link = wrapper.findAll('a').find((a) => a.text() === 'Читати EPUB')
    expect(link?.attributes('href')).toBe('/reader/9')
  })

  it('has no EPUB button without a linked document', async () => {
    const entry = makeUserBook({ id: 3 })
    vi.mocked(libraryApi.get).mockResolvedValue({ ...entry, reader_document_id: null })
    const { wrapper } = await mountView(entry)

    expect(wrapper.text()).not.toContain('Читати EPUB')
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

  it('keeps the book and says so when removing fails', async () => {
    const { wrapper, router } = await mountView(makeUserBook())
    vi.mocked(libraryApi.remove).mockRejectedValue(new ApiError(500, null))

    await buttonWithText(wrapper, 'Видалити з бібліотеки').trigger('click')
    await buttonWithText(wrapper, 'Видалити').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
    expect(wrapper.text()).toContain('Atomic Habits')
    expect(router.currentRoute.value.name).toBe('book')
  })

  it('offers a retry when the book cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockResolvedValue([])
    vi.mocked(libraryApi.get).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(makeUserBook({ id: 5 }))
    vi.mocked(libraryApi.logs).mockResolvedValue({ data: [], links: { next: null } })
    await router.push({ name: 'book', params: { id: 5 } })

    const wrapper = mount(BookView, { props: { id: 5 }, global: { plugins } })
    await flushPromises()
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Atomic Habits')
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
      const { wrapper } = await mountView(makeUserBook({ status: 'want', allowed_statuses: [] }))

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

  describe('review', () => {
    const finished = (overrides: Partial<UserBook> = {}) =>
      makeUserBook({ id: 1, status: 'finished', allowed_statuses: ['reading'], ...overrides })

    it('is offered only for a finished or abandoned book', async () => {
      expect((await mountView(makeUserBook({ status: 'reading', allowed_statuses: [] }))).wrapper.find('#book-review').exists()).toBe(false)
      expect((await mountView(makeUserBook({ status: 'want', allowed_statuses: [] }))).wrapper.find('#book-review').exists()).toBe(false)
      expect((await mountView(finished())).wrapper.find('#book-review').exists()).toBe(true)
      expect((await mountView(makeUserBook({ status: 'abandoned', allowed_statuses: [] }))).wrapper.find('#book-review').exists()).toBe(true)
    })

    it('counts characters, saves with the visibility and then shows the text read-only', async () => {
      const entry = finished()
      const { wrapper } = await mountView(entry)
      let finish: (book: UserBook) => void = () => undefined
      vi.mocked(libraryApi.update).mockImplementation(() => new Promise((resolve) => (finish = resolve)))

      await wrapper.get('#book-review').setValue('Чудова\nкнига')
      expect(wrapper.get('#book-review-counter').text()).toBe('12 з 5000')
      expect(wrapper.get('#book-review').attributes('maxlength')).toBe('5000')

      await buttonWithText(wrapper, 'Усі').trigger('click')
      await wrapper.get('form[class*="gap-3"]').trigger('submit')
      expect(buttonWithText(wrapper, 'Зберігаємо…').attributes('disabled')).toBeDefined()
      expect(libraryApi.update).toHaveBeenCalledWith(1, { review: 'Чудова\nкнига', review_visibility: 'public' })

      finish({ ...entry, review: 'Чудова\nкнига', review_visibility: 'public', reviewed_at: '2026-10-05T10:00:00+00:00' })
      await flushPromises()

      expect(wrapper.find('#book-review').exists()).toBe(false)
      expect(wrapper.get('p.whitespace-pre-line').text()).toBe('Чудова\nкнига')
      expect(buttonWithText(wrapper, 'Редагувати')).toBeDefined()
    })

    it('shows a saved review read-only and edits it on request', async () => {
      const { wrapper } = await mountView(finished({ review: 'Було <b>добре</b>', review_visibility: 'private' }))

      expect(wrapper.text()).toContain('Було <b>добре</b>')
      expect(wrapper.find('b').exists()).toBe(false)
      expect(wrapper.find('#book-review').exists()).toBe(false)

      await buttonWithText(wrapper, 'Редагувати').trigger('click')
      expect((wrapper.get('#book-review').element as HTMLTextAreaElement).value).toBe('Було <b>добре</b>')

      await buttonWithText(wrapper, 'Скасувати').trigger('click')
      expect(wrapper.find('#book-review').exists()).toBe(false)
    })

    it('keeps the form and explains when the server turns the review down', async () => {
      const { wrapper } = await mountView(finished())
      vi.mocked(libraryApi.update).mockRejectedValue(new ApiError(422, { errors: { review: ['library.review_not_finished'] } }))

      await wrapper.get('#book-review').setValue('Рано')
      await wrapper.get('form[class*="gap-3"]').trigger('submit')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Відгук можна написати, коли книгу прочитано або кинуто.')
      expect((wrapper.get('#book-review').element as HTMLTextAreaElement).value).toBe('Рано')
    })

    it('warns that a public review of a private profile will not be seen', async () => {
      const { wrapper } = await mountView(finished())
      useAuthStore().user = { id: 1, name: 'Іван', email: 'i@example.com', timezone: 'Europe/Kyiv', username: null, is_public: false }

      await buttonWithText(wrapper, 'Усі').trigger('click')

      expect(wrapper.text()).toContain('Ваш профіль приватний')
    })
  })
})
