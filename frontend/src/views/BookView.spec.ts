import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { useLibraryStore } from '@/stores/library'
import { makeQuote, makeUserBook } from '@/test/fixtures'
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
      meta: { pages: 50, reached_end: false },
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
      meta: { pages: 20, reached_end: false },
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
})
