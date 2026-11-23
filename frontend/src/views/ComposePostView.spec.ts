import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { socialApi } from '@/api/social'
import { useFeedStore } from '@/stores/feed'
import { makeBook, makeQuote, makeUserBook } from '@/test/fixtures'
import { makePost } from '@/test/socialFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { UserBook, Quote } from '@/types/api'
import ComposePostView from './ComposePostView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))
vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))
vi.mock('@/api/social', () => ({
  socialApi: { createPost: vi.fn() },
}))

const books = [
  makeUserBook({ id: 1, book: makeBook({ id: 11, title: 'Яблуко' }) }),
  makeUserBook({ id: 2, book: makeBook({ id: 12, title: 'Атомні звички' }) }),
]

const myQuotes = [
  makeQuote({ id: 5, content: 'Fifth quote', book: { title: 'Атомні звички', authors: [], cover_url: null } }),
  makeQuote({ id: 4, content: 'Fourth quote', book: { title: 'Яблуко', authors: [], cover_url: null } }),
]

async function mountView(
  query: Record<string, string> = {},
  options: { books?: UserBook[]; quotes?: Quote[]; next?: string | null } = {},
) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue(options.books ?? books)
  vi.mocked(quotesApi.list).mockResolvedValue({ data: options.quotes ?? myQuotes, links: { next: options.next ?? null } })
  await router.push({ name: 'community-new', query })

  const wrapper = mount(ComposePostView, { global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

function quoteRadios(wrapper: ReturnType<typeof mount>) {
  return wrapper.findAll('input[name="post-quote"]')
}

describe('ComposePostView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('says what publishing means', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Публікацію побачать усі читачі Bookshelf')
  })

  it('shows a loading note until the quotes and the library are loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockReturnValue(new Promise(() => {}))
    vi.mocked(quotesApi.list).mockReturnValue(new Promise(() => {}))
    await router.push({ name: 'community-new' })

    const wrapper = mount(ComposePostView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.get('[role="status"]').text()).toContain('Завантаження…')
    expect(wrapper.find('form').exists()).toBe(false)
  })

  it('says why and offers a retry when the data cannot be loaded', async () => {
    const { plugins, router } = createTestEnvironment()
    vi.mocked(libraryApi.list).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(books)
    vi.mocked(quotesApi.list).mockResolvedValue({ data: myQuotes, links: { next: null } })
    await router.push({ name: 'community-new' })
    const wrapper = mount(ComposePostView, { global: { plugins } })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

    await buttonWithText(wrapper, 'Повторити').trigger('click')
    await flushPromises()

    expect(wrapper.find('form').exists()).toBe(true)
  })

  describe('publishing a quote', () => {
    it('starts with the quotes to choose from, none chosen', async () => {
      const { wrapper } = await mountView()

      expect(buttonWithText(wrapper, 'Цитату').attributes('aria-pressed')).toBe('true')
      expect(quoteRadios(wrapper)).toHaveLength(2)
      expect(quoteRadios(wrapper).map((radio) => (radio.element as HTMLInputElement).checked)).toEqual([false, false])
      expect(wrapper.text()).toContain('Fifth quote')
      expect(wrapper.text()).toContain('Атомні звички')
    })

    it('publishes the chosen quote with a comment, and opens the post', async () => {
      const { wrapper, router } = await mountView()
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 31, is_mine: true }))

      await quoteRadios(wrapper)[1]!.setValue(true)
      await wrapper.get('#post-comment').setValue('  This one stayed with me.  ')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).toHaveBeenCalledWith({ type: 'quote', quote_id: 4, comment: 'This one stayed with me.' })
      expect(router.currentRoute.value.fullPath).toBe('/community/posts/31')
    })

    it('does not need a comment', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 31 }))

      await quoteRadios(wrapper)[0]!.setValue(true)
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).toHaveBeenCalledWith({ type: 'quote', quote_id: 5, comment: null })
    })

    it('puts the new post at the top of a feed that is loaded', async () => {
      const { wrapper } = await mountView()
      const feed = useFeedStore()
      vi.spyOn(feed, 'prepend')
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 31 }))

      await quoteRadios(wrapper)[0]!.setValue(true)
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(feed.prepend).toHaveBeenCalledWith(expect.objectContaining({ id: 31 }))
    })

    it('does not publish without a quote, and says so', async () => {
      const { wrapper } = await mountView()

      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).not.toHaveBeenCalled()
      expect(wrapper.get('#post-quote-error').text()).toBe('Оберіть цитату зі свого списку.')
    })

    it('tells that the post is a copy of the text', async () => {
      const { wrapper } = await mountView()

      expect(wrapper.text()).toContain('Опублікується копія тексту')
    })

    it('counts the characters of the comment, up to 2000', async () => {
      const { wrapper } = await mountView()

      await wrapper.get('#post-comment').setValue('abc')

      expect(wrapper.text()).toContain('3 з 2000')
      expect(wrapper.get('#post-comment').attributes('maxlength')).toBe('2000')
    })

    it('shows a refused quote next to the list', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.createPost).mockRejectedValue(new ApiError(422, { errors: { quote_id: ['The selected quote id is invalid.'] } }))

      await quoteRadios(wrapper)[0]!.setValue(true)
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('#post-quote-error').text()).toBe('Оберіть цитату зі свого списку.')
    })

    it('says why and keeps the form when publishing fails', async () => {
      const { wrapper, router } = await mountView()
      vi.mocked(socialApi.createPost).mockRejectedValue(new ApiError(429, null))

      await quoteRadios(wrapper)[0]!.setValue(true)
      await wrapper.get('#post-comment').setValue('Mine')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Забагато запитів')
      expect((wrapper.get('#post-comment').element as HTMLTextAreaElement).value).toBe('Mine')
      expect(router.currentRoute.value.name).toBe('community-new')
      expect(buttonWithText(wrapper, 'Опублікувати').attributes('disabled')).toBeUndefined()
    })

    it('cannot be sent twice at once', async () => {
      const { wrapper } = await mountView()
      vi.mocked(socialApi.createPost).mockReturnValue(new Promise(() => {}))

      await quoteRadios(wrapper)[0]!.setValue(true)
      await wrapper.get('form').trigger('submit')

      expect(buttonWithText(wrapper, 'Публікуємо…').attributes('disabled')).toBeDefined()
    })

    it('offers more quotes when there are more', async () => {
      const { wrapper } = await mountView({}, { next: '/api/quotes?page=2' })
      vi.mocked(quotesApi.list).mockResolvedValue({ data: [makeQuote({ id: 3, content: 'Third quote' })], links: { next: null } })

      await buttonWithText(wrapper, 'Показати ще').trigger('click')
      await flushPromises()

      expect(quoteRadios(wrapper)).toHaveLength(3)
      expect(wrapper.text()).toContain('Third quote')
    })

    it('sends the reader to the quotes when they have none', async () => {
      const { wrapper } = await mountView({}, { quotes: [] })

      expect(wrapper.text()).toContain('У вас ще немає цитат')
      expect(wrapper.findAll('a').find((link) => link.text() === 'До цитат')?.attributes('href')).toBe('/quotes')
      expect(wrapper.findAll('button').some((button) => button.text() === 'Опублікувати')).toBe(false)
    })
  })

  describe('a quote the reader came with', () => {
    it('is already chosen when it is in the list', async () => {
      const { wrapper } = await mountView({ quote: '4' })

      expect(quoteRadios(wrapper).map((radio) => (radio.element as HTMLInputElement).checked)).toEqual([false, true])
      expect(quotesApi.get).not.toHaveBeenCalled()
    })

    it('is fetched, chosen and shown first when it is not in the list', async () => {
      vi.mocked(quotesApi.get).mockResolvedValue(makeQuote({ id: 99, content: 'An older quote' }))
      const { wrapper } = await mountView({ quote: '99' })

      expect(quotesApi.get).toHaveBeenCalledWith(99)
      expect(quoteRadios(wrapper)).toHaveLength(3)
      expect((quoteRadios(wrapper)[0]!.element as HTMLInputElement).checked).toBe(true)
      expect(wrapper.findAll('label')[0]!.text()).toContain('An older quote')
    })

    it('publishes the quote it came with', async () => {
      const { wrapper } = await mountView({ quote: '4' })
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 31 }))

      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).toHaveBeenCalledWith({ type: 'quote', quote_id: 4, comment: null })
    })

    it('says so when it is not among the reader\'s quotes', async () => {
      vi.mocked(quotesApi.get).mockRejectedValue(new ApiError(404, null))
      const { wrapper } = await mountView({ quote: '99' })

      expect(wrapper.get('[role="alert"]').text()).toContain('Цієї цитати не знайдено серед ваших.')
      expect(quoteRadios(wrapper)).toHaveLength(2)
      expect(quoteRadios(wrapper).every((radio) => !(radio.element as HTMLInputElement).checked)).toBe(true)
    })
  })

  describe('writing a review', () => {
    it('opens on a review when it comes with a book of the library', async () => {
      const { wrapper } = await mountView({ book: '2' })

      expect(buttonWithText(wrapper, 'Відгук').attributes('aria-pressed')).toBe('true')
      expect((wrapper.get('#post-book').element as HTMLSelectElement).value).toBe('2')
    })

    it('lists the books of the library alphabetically', async () => {
      const { wrapper } = await mountView({ book: '2' })

      expect(wrapper.findAll('#post-book option').map((option) => option.text())).toEqual(['Оберіть книгу', 'Атомні звички', 'Яблуко'])
    })

    it('switches between the two kinds', async () => {
      const { wrapper } = await mountView()

      await buttonWithText(wrapper, 'Відгук').trigger('click')
      expect(wrapper.find('#post-book').exists()).toBe(true)
      expect(quoteRadios(wrapper)).toHaveLength(0)

      await buttonWithText(wrapper, 'Цитату').trigger('click')
      expect(wrapper.find('#post-book').exists()).toBe(false)
      expect(quoteRadios(wrapper)).toHaveLength(2)
    })

    it('publishes the rating and the text, and opens the post', async () => {
      const { wrapper, router } = await mountView({ book: '2' })
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 41, type: 'review', rating: 4 }))

      await wrapper.get('[aria-label="4 / 5"]').trigger('click')
      await wrapper.get('#post-content').setValue('  Worth reading twice.  ')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).toHaveBeenCalledWith({ type: 'review', user_book_id: 2, rating: 4, content: 'Worth reading twice.' })
      expect(router.currentRoute.value.fullPath).toBe('/community/posts/41')
    })

    it('allows a rating alone', async () => {
      const { wrapper } = await mountView({ book: '1' })
      vi.mocked(socialApi.createPost).mockResolvedValue(makePost({ id: 41 }))

      await wrapper.get('[aria-label="5 / 5"]').trigger('click')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).toHaveBeenCalledWith({ type: 'review', user_book_id: 1, rating: 5, content: null })
    })

    it('does not publish without a rating, and says so', async () => {
      const { wrapper } = await mountView({ book: '1' })

      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).not.toHaveBeenCalled()
      expect(wrapper.text()).toContain('Поставте оцінку від 1 до 5.')
    })

    it('does not publish without a book, and says so', async () => {
      const { wrapper } = await mountView()
      await buttonWithText(wrapper, 'Відгук').trigger('click')
      await wrapper.get('[aria-label="3 / 5"]').trigger('click')

      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(socialApi.createPost).not.toHaveBeenCalled()
      expect(wrapper.get('#post-book-error').text()).toBe('Оберіть книгу зі своєї бібліотеки.')
    })

    it('counts the characters of the text, up to 5000', async () => {
      const { wrapper } = await mountView({ book: '1' })

      await wrapper.get('#post-content').setValue('abcd')

      expect(wrapper.text()).toContain('4 з 5000')
      expect(wrapper.get('#post-content').attributes('maxlength')).toBe('5000')
    })

    it('says that a book can have only one review', async () => {
      const { wrapper } = await mountView({ book: '1' })
      vi.mocked(socialApi.createPost).mockRejectedValue(new ApiError(422, { errors: { user_book_id: ['social.review_exists'] } }))

      await wrapper.get('[aria-label="3 / 5"]').trigger('click')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('#post-book-error').text()).toBe('Ви вже написали відгук про цю книгу. Змініть його на сторінці публікації.')
      expect(wrapper.get('#post-book').attributes('aria-invalid')).toBe('true')
    })

    it('shows a refused text next to the field', async () => {
      const { wrapper } = await mountView({ book: '1' })
      vi.mocked(socialApi.createPost).mockRejectedValue(new ApiError(422, { errors: { content: ['The content field must not be greater than 5000 characters.'] } }))

      await wrapper.get('[aria-label="3 / 5"]').trigger('click')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('#post-content-error').text()).toBe('Відгук має бути не довшим за 5000 символів.')
    })

    it('sends the reader to the library when it is empty', async () => {
      const { wrapper } = await mountView({}, { books: [] })
      await buttonWithText(wrapper, 'Відгук').trigger('click')

      expect(wrapper.text()).toContain('Щоб написати відгук, спершу додайте книгу до бібліотеки.')
      expect(wrapper.findAll('a').find((link) => link.text() === 'До бібліотеки')?.attributes('href')).toBe('/library')
      expect(wrapper.findAll('button').some((button) => button.text() === 'Опублікувати')).toBe(false)
    })

    it('ignores a book that is not in the library', async () => {
      const { wrapper } = await mountView({ book: '77' })

      expect((wrapper.get('#post-book').element as HTMLSelectElement).value).toBe('')
    })
  })

  describe('leaving', () => {
    it('goes back to the community on cancel when there is nowhere to go back to', async () => {
      const { wrapper, router } = await mountView()

      await buttonWithText(wrapper, 'Скасувати').trigger('click')
      await flushPromises()

      expect(router.currentRoute.value.name).toBe('community')
    })
  })
})
