import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { makeBook, makeQuote, makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { Quote, QuotesPage } from '@/types/api'
import QuotesView from './QuotesView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))
vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

const books = [
  makeUserBook({ id: 1, book: makeBook({ title: 'Яблуко' }) }),
  makeUserBook({ id: 2, book: makeBook({ title: 'Атомні звички' }) }),
]

function page(quotes: Quote[], next: string | null = null): QuotesPage {
  return { data: quotes, links: { next } }
}

async function mountView(query: Record<string, string> = {}, firstPage: QuotesPage = page([makeQuote({ id: 1 })])) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue(books)
  vi.mocked(quotesApi.list).mockResolvedValue(firstPage)
  await router.push({ name: 'quotes', query })

  const wrapper = mount(QuotesView, { global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('QuotesView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('lists the quotes, newest first as the server sends them', async () => {
    const { wrapper } = await mountView(
      {},
      page([makeQuote({ id: 2, content: 'Second' }), makeQuote({ id: 1, content: 'First' })]),
    )

    expect(quotesApi.list).toHaveBeenCalledWith({}, 1)
    expect(wrapper.findAll('blockquote').map((quote) => quote.text())).toEqual(['Second', 'First'])
  })

  it('offers adding a quote', async () => {
    const { wrapper } = await mountView()

    const add = wrapper.findAll('a').find((link) => link.text() === 'Додати')

    expect(add?.attributes('href')).toBe('/quotes/new')
  })

  it('shows a loading note until the first answer', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockResolvedValue(books)
    vi.mocked(quotesApi.list).mockReturnValue(new Promise(() => {}))
    await router.push({ name: 'quotes' })

    const wrapper = mount(QuotesView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.text()).toContain('Завантаження…')
  })

  describe('filters', () => {
    it('start on "all quotes" and all books', async () => {
      const { wrapper } = await mountView()

      expect(buttonWithText(wrapper, 'Усі').attributes('aria-pressed')).toBe('true')
      expect(buttonWithText(wrapper, 'Улюблені').attributes('aria-pressed')).toBe('false')
      expect((wrapper.get('#quotes-book').element as HTMLSelectElement).value).toBe('')
    })

    it('list the books of the library alphabetically', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.findAll('#quotes-book option').map((option) => option.text())).toEqual([
        'Усі книги',
        'Атомні звички',
        'Яблуко',
      ])
    })

    it('narrow the list to favorites and remember it in the URL', async () => {
      const { wrapper, router } = await mountView()
      vi.mocked(quotesApi.list).mockResolvedValue(page([makeQuote({ id: 5, is_favorite: true })]))

      await buttonWithText(wrapper, 'Улюблені').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({ favorite: '1' })
      expect(quotesApi.list).toHaveBeenLastCalledWith({ favorite: true }, 1)
      expect(buttonWithText(wrapper, 'Улюблені').attributes('aria-pressed')).toBe('true')
      expect(wrapper.findAll('blockquote')).toHaveLength(1)
    })

    it('go back to all quotes', async () => {
      const { wrapper, router } = await mountView({ favorite: '1' })

      await buttonWithText(wrapper, 'Усі').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({})
      expect(quotesApi.list).toHaveBeenLastCalledWith({}, 1)
    })

    it('narrow the list to one book and remember it in the URL', async () => {
      const { wrapper, router } = await mountView()

      await wrapper.get('#quotes-book').setValue(2)
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({ book: '2' })
      expect(quotesApi.list).toHaveBeenLastCalledWith({ book: 2 }, 1)
    })

    it('combine, keeping one when the other changes', async () => {
      const { wrapper, router } = await mountView({ favorite: '1' })

      await wrapper.get('#quotes-book').setValue(1)
      await flushPromises()
      expect(router.currentRoute.value.query).toEqual({ favorite: '1', book: '1' })

      await buttonWithText(wrapper, 'Усі').trigger('click')
      await flushPromises()
      expect(router.currentRoute.value.query).toEqual({ book: '1' })

      await wrapper.get('#quotes-book').setValue('')
      await flushPromises()
      expect(router.currentRoute.value.query).toEqual({})
    })

    it('are restored from the URL', async () => {
      const { wrapper } = await mountView({ favorite: '1', book: '2' })

      expect(quotesApi.list).toHaveBeenCalledWith({ favorite: true, book: 2 }, 1)
      expect(buttonWithText(wrapper, 'Улюблені').attributes('aria-pressed')).toBe('true')
      expect((wrapper.get('#quotes-book').element as HTMLSelectElement).value).toBe('2')
    })

    it('ignore nonsense in the URL', async () => {
      await mountView({ favorite: 'yes', book: 'abc' })

      expect(quotesApi.list).toHaveBeenCalledWith({}, 1)
    })

    it('pass the chosen book on to a new quote', async () => {
      const { wrapper } = await mountView({ book: '2' })

      const add = wrapper.findAll('a').find((link) => link.text() === 'Додати')

      expect(add?.attributes('href')).toBe('/quotes/new?book=2')
    })

    it('do not trigger a load when the reader leaves for another screen', async () => {
      const { router } = await mountView({ book: '2' })

      await router.push({ name: 'quote-new', query: { book: '1' } })
      await flushPromises()

      expect(quotesApi.list).toHaveBeenCalledTimes(1)
    })
  })

  describe('with nothing to show', () => {
    it('invites the reader to add the first quote', async () => {
      const { wrapper } = await mountView({}, page([]))

      expect(wrapper.text()).toContain('Ще немає цитат')
      expect(wrapper.findAll('a').some((link) => link.attributes('href') === '/quotes/new' && link.text() === 'Додати цитату')).toBe(true)
    })

    it('says nothing matched when filters are on, and lets the reader clear them', async () => {
      const { wrapper, router } = await mountView({ favorite: '1' }, page([]))

      expect(wrapper.text()).toContain('Нічого не знайшли')
      expect(wrapper.text()).not.toContain('Ще немає цитат')

      vi.mocked(quotesApi.list).mockResolvedValue(page([makeQuote()]))
      await buttonWithText(wrapper, 'Скинути фільтри').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.query).toEqual({})
      expect(wrapper.findAll('blockquote')).toHaveLength(1)
    })
  })

  describe('load more', () => {
    it('appends the next page and hides the button after the last one', async () => {
      const { wrapper } = await mountView({}, page([makeQuote({ id: 2 })], '/api/quotes?page=2'))
      vi.mocked(quotesApi.list).mockResolvedValue(page([makeQuote({ id: 1 })]))
      expect(wrapper.findAll('blockquote')).toHaveLength(1)

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(quotesApi.list).toHaveBeenLastCalledWith({}, 2)
      expect(wrapper.findAll('blockquote')).toHaveLength(2)
      expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
    })

    it('has no button when everything fits on one page', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.findAll('button').some((button) => button.text() === 'Показати ще')).toBe(false)
    })

    it('keeps the list and offers a retry when the next page fails', async () => {
      const { wrapper } = await mountView({}, page([makeQuote({ id: 2 })], '/api/quotes?page=2'))
      vi.mocked(quotesApi.list).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(page([makeQuote({ id: 1 })]))

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      expect(wrapper.findAll('blockquote')).toHaveLength(1)

      await wrapper.get('[role="alert"] button').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.findAll('blockquote')).toHaveLength(2)
    })
  })

  it('offers a retry when the quotes cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockResolvedValue(books)
    vi.mocked(quotesApi.list).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(page([makeQuote()]))
    await router.push({ name: 'quotes' })
    const wrapper = mount(QuotesView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
    expect(wrapper.find('blockquote').exists()).toBe(false)

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.findAll('blockquote')).toHaveLength(1)
  })

  it('still lists the quotes when the library cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockRejectedValue(new ApiError(500, null))
    vi.mocked(quotesApi.list).mockResolvedValue(page([makeQuote()]))
    await router.push({ name: 'quotes' })

    const wrapper = mount(QuotesView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.findAll('blockquote')).toHaveLength(1)
    expect(wrapper.findAll('#quotes-book option')).toHaveLength(1)
  })
})
