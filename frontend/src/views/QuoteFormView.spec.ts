import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { useQuotesStore } from '@/stores/quotes'
import { makeBook, makeQuote, makeUserBook } from '@/test/fixtures'
import { createTestEnvironment } from '@/test/utils'
import type { UserBook } from '@/types/api'
import QuoteFormView from './QuoteFormView.vue'

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))
vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

const library = [
  makeUserBook({ id: 1, total_pages: 320, book: makeBook({ title: 'Яблуко' }) }),
  makeUserBook({ id: 2, total_pages: null, book: makeBook({ title: 'Атомні звички' }) }),
]

async function mountCreate(query: Record<string, string> = {}, books: UserBook[] = library) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue(books)
  await router.push({ name: 'quote-new', query })

  const wrapper = mount(QuoteFormView, { global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

async function mountEdit(id = 5) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue(library)
  await router.push({ name: 'quote-edit', params: { id } })

  const wrapper = mount(QuoteFormView, { props: { id }, global: { plugins } })
  await flushPromises()

  return { wrapper, router }
}

function field(wrapper: ReturnType<typeof mount>, selector: string) {
  return wrapper.get<HTMLInputElement>(selector)
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('QuoteFormView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    window.history.replaceState(null, '')
  })

  describe('adding a quote', () => {
    it('asks for the book among the books of the library, alphabetically', async () => {
      const { wrapper } = await mountCreate()

      expect(wrapper.get('h1').text()).toBe('Нова цитата')
      expect(wrapper.findAll('#quote-book option').map((option) => option.text())).toEqual([
        'Оберіть книгу',
        'Атомні звички',
        'Яблуко',
      ])
      expect(field(wrapper, '#quote-book').element.value).toBe('')
    })

    it('preselects the book from the link that brought the reader here', async () => {
      const { wrapper } = await mountCreate({ book: '2' })

      expect(field(wrapper, '#quote-book').element.value).toBe('2')
    })

    it('does not preselect a book that is not in the library', async () => {
      const { wrapper } = await mountCreate({ book: '99' })

      expect(field(wrapper, '#quote-book').element.value).toBe('')
    })

    it('points to the library when there is no book to add a quote to', async () => {
      const { wrapper } = await mountCreate({}, [])

      expect(wrapper.text()).toContain('спершу додайте книгу до бібліотеки')
      expect(wrapper.find('form').exists()).toBe(false)
      expect(wrapper.findAll('a').some((link) => link.attributes('href') === '/library' && link.text() === 'До бібліотеки')).toBe(true)
    })

    it('has real labels on every field', async () => {
      const { wrapper } = await mountCreate()

      const labels = wrapper.findAll('label').map((label) => [label.text(), label.attributes('for')])

      expect(labels).toEqual([
        ['Книга', 'quote-book'],
        ['Текст', 'quote-content'],
        ['Моя думка', 'quote-note'],
        ['Сторінка', 'quote-page'],
      ])
    })

    it('counts the characters of the text', async () => {
      const { wrapper } = await mountCreate()
      expect(wrapper.text()).toContain('0 з 2000')

      await wrapper.get('#quote-content').setValue('Hello')

      expect(wrapper.text()).toContain('5 з 2000')
      expect(wrapper.get('#quote-content').attributes('maxlength')).toBe('2000')
      expect(wrapper.get('#quote-note').attributes('maxlength')).toBe('2000')
    })

    it('limits the page to the pages of the chosen book, or to what the server accepts', async () => {
      const { wrapper } = await mountCreate()
      expect(field(wrapper, '#quote-page').attributes('max')).toBe('20000')

      await wrapper.get('#quote-book').setValue(1)
      expect(field(wrapper, '#quote-page').attributes('max')).toBe('320')

      await wrapper.get('#quote-book').setValue(2)
      expect(field(wrapper, '#quote-page').attributes('max')).toBe('20000')
    })

    it('switches between a quote and an insight', async () => {
      const { wrapper } = await mountCreate()

      expect(buttonWithText(wrapper, 'Цитата').attributes('aria-pressed')).toBe('true')
      expect(buttonWithText(wrapper, 'Інсайт').attributes('aria-pressed')).toBe('false')

      await buttonWithText(wrapper, 'Інсайт').trigger('click')

      expect(buttonWithText(wrapper, 'Цитата').attributes('aria-pressed')).toBe('false')
      expect(buttonWithText(wrapper, 'Інсайт').attributes('aria-pressed')).toBe('true')
    })

    it('saves the quote under the chosen book and leaves for the list', async () => {
      const { wrapper, router } = await mountCreate({ book: '1' })
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 8 }))

      await buttonWithText(wrapper, 'Інсайт').trigger('click')
      await wrapper.get('#quote-content').setValue('  A thought worth keeping.  ')
      await wrapper.get('#quote-note').setValue('Mine.')
      await wrapper.get('#quote-page').setValue('42')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledWith(1, {
        type: 'insight',
        content: 'A thought worth keeping.',
        note: 'Mine.',
        page: 42,
      })
      expect(router.currentRoute.value.name).toBe('quotes')
    })

    it('sends an empty thought and an empty page as nothing', async () => {
      const { wrapper } = await mountCreate({ book: '1' })
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote())

      await wrapper.get('#quote-content').setValue('Just the text')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledWith(1, { type: 'quote', content: 'Just the text', note: null, page: null })
    })

    it('goes back to where the reader came from when there is somewhere to go back to', async () => {
      const { wrapper, router } = await mountCreate({ book: '1' })
      const back = vi.spyOn(router, 'back').mockImplementation(() => {})
      window.history.replaceState({ back: '/library/1' }, '')
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote())

      await wrapper.get('#quote-content').setValue('Text')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(back).toHaveBeenCalledTimes(1)
      expect(router.currentRoute.value.name).toBe('quote-new')
    })

    it('goes to the list on cancel when there is nowhere to go back to', async () => {
      const { wrapper, router } = await mountCreate()

      await buttonWithText(wrapper, 'Скасувати').trigger('click')
      await flushPromises()

      expect(quotesApi.create).not.toHaveBeenCalled()
      expect(router.currentRoute.value.name).toBe('quotes')
    })

    it('goes back on cancel when there is somewhere to go back to', async () => {
      const { wrapper, router } = await mountCreate()
      const back = vi.spyOn(router, 'back').mockImplementation(() => {})
      window.history.replaceState({ back: '/library/1' }, '')

      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(back).toHaveBeenCalledTimes(1)
    })

    it('cannot be sent twice while saving', async () => {
      const { wrapper } = await mountCreate({ book: '1' })
      vi.mocked(quotesApi.create).mockReturnValue(new Promise(() => {}))

      await wrapper.get('#quote-content').setValue('Text')
      await wrapper.get('form').trigger('submit')

      expect(buttonWithText(wrapper, 'Зберегти').attributes('disabled')).toBeDefined()
    })

    it('puts the new quote into the list of the quotes screen', async () => {
      const { wrapper } = await mountCreate({ book: '1' })
      const quotes = useQuotesStore()
      vi.mocked(quotesApi.list).mockResolvedValue({ data: [makeQuote({ id: 1 })], links: { next: null } })
      await quotes.load()
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 2 }))

      await wrapper.get('#quote-content').setValue('Text')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(quotes.items.map((item) => item.id)).toEqual([2, 1])
    })

    describe('when the server says no', () => {
      async function submitRejected(error: unknown) {
        const view = await mountCreate({ book: '1' })
        vi.mocked(quotesApi.create).mockRejectedValue(error)

        await view.wrapper.get('#quote-content').setValue('Text')
        await view.wrapper.get('form').trigger('submit')
        await flushPromises()

        return view
      }

      it('explains a page beyond the end of the book next to the page field', async () => {
        const { wrapper, router } = await submitRejected(
          new ApiError(422, { message: 'Invalid', errors: { page: ['quotes.page_above_total'] } }),
        )

        expect(wrapper.get('#quote-page-error').text()).toBe('На цій сторінці немає такої цитати: у книзі менше сторінок.')
        expect(wrapper.get('#quote-page').attributes('aria-invalid')).toBe('true')
        expect(wrapper.get('#quote-page').attributes('aria-describedby')).toBe('quote-page-error')
        expect(wrapper.find('[role="alert"]').exists()).toBe(false)
        expect(router.currentRoute.value.name).toBe('quote-new')
      })

      it('puts each error next to its own field', async () => {
        const { wrapper } = await submitRejected(
          new ApiError(422, {
            message: 'Invalid',
            errors: {
              content: ['The content field is required.'],
              note: ['The note field must not be greater than 2000 characters.'],
            },
          }),
        )

        expect(wrapper.get('#quote-content-error').text()).toBe('Введіть текст, не довший за 2000 символів.')
        expect(wrapper.get('#quote-note-error').text()).toBe('Думка має бути не довшою за 2000 символів.')
        expect(wrapper.find('#quote-page-error').exists()).toBe(false)
      })

      it('clears the errors on the next try', async () => {
        const { wrapper } = await submitRejected(new ApiError(422, { errors: { content: ['The content field is required.'] } }))
        vi.mocked(quotesApi.create).mockReturnValue(new Promise(() => {}))

        await wrapper.get('form').trigger('submit')

        expect(wrapper.find('#quote-content-error').exists()).toBe(false)
      })

      it('shows a general notice for a problem that belongs to no field', async () => {
        const { wrapper } = await submitRejected(new ApiError(422, { errors: { type: ['The selected type is invalid.'] } }))

        expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
      })

      it('shows a general notice for any other failure and lets the reader try again', async () => {
        const { wrapper } = await submitRejected(new ApiError(500, null))

        expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')
        expect(buttonWithText(wrapper, 'Зберегти').attributes('disabled')).toBeUndefined()
        expect(wrapper.get<HTMLTextAreaElement>('#quote-content').element.value).toBe('Text')
      })
    })
  })

  describe('editing a quote', () => {
    const existing = makeQuote({
      id: 5,
      type: 'insight',
      content: 'Old text',
      note: 'Old thought',
      page: 12,
      user_book_id: 1,
      book: { title: 'Яблуко', authors: [], cover_url: null },
    })

    it('fills the form with the quote from the server', async () => {
      vi.mocked(quotesApi.get).mockResolvedValue(existing)
      const { wrapper } = await mountEdit()

      expect(quotesApi.get).toHaveBeenCalledWith(5)
      expect(wrapper.get('h1').text()).toBe('Редагування')
      expect(field(wrapper, '#quote-content').element.value).toBe('Old text')
      expect(field(wrapper, '#quote-note').element.value).toBe('Old thought')
      expect(field(wrapper, '#quote-page').element.value).toBe('12')
      expect(buttonWithText(wrapper, 'Інсайт').attributes('aria-pressed')).toBe('true')
    })

    it('takes the quote from the list when it is there', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue(library)
      vi.mocked(quotesApi.list).mockResolvedValue({ data: [existing], links: { next: null } })
      await useQuotesStore().load()
      await router.push({ name: 'quote-edit', params: { id: 5 } })

      const wrapper = mount(QuoteFormView, { props: { id: 5 }, global: { plugins } })
      await flushPromises()

      expect(quotesApi.get).not.toHaveBeenCalled()
      expect(field(wrapper, '#quote-content').element.value).toBe('Old text')
    })

    it('shows the book but does not let the reader change it', async () => {
      vi.mocked(quotesApi.get).mockResolvedValue(existing)
      const { wrapper } = await mountEdit()

      expect(wrapper.find('#quote-book').exists()).toBe(false)
      expect(wrapper.text()).toContain('Яблуко')
    })

    it('limits the page to the pages of the quote\'s book', async () => {
      vi.mocked(quotesApi.get).mockResolvedValue(existing)
      const { wrapper } = await mountEdit()

      expect(field(wrapper, '#quote-page').attributes('max')).toBe('320')
    })

    it('saves the changes and updates the list', async () => {
      vi.mocked(quotesApi.get).mockResolvedValue(existing)
      const { wrapper, router } = await mountEdit()
      vi.mocked(quotesApi.update).mockResolvedValue({ ...existing, content: 'New text' })

      await buttonWithText(wrapper, 'Цитата').trigger('click')
      await wrapper.get('#quote-content').setValue('New text')
      await wrapper.get('#quote-note').setValue('')
      await wrapper.get('#quote-page').setValue('')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(quotesApi.update).toHaveBeenCalledWith(5, { type: 'quote', content: 'New text', note: null, page: null })
      expect(quotesApi.create).not.toHaveBeenCalled()
      expect(router.currentRoute.value.name).toBe('quotes')
    })

    it('explains a page beyond the end of the book', async () => {
      vi.mocked(quotesApi.get).mockResolvedValue(existing)
      const { wrapper } = await mountEdit()
      vi.mocked(quotesApi.update).mockRejectedValue(
        new ApiError(422, { message: 'Invalid', errors: { page: ['quotes.page_above_total'] } }),
      )

      await wrapper.get('#quote-page').setValue('9999')
      await wrapper.get('form').trigger('submit')
      await flushPromises()

      expect(wrapper.get('#quote-page-error').text()).toContain('у книзі менше сторінок')
    })

    it('says so when the quote does not exist', async () => {
      vi.mocked(quotesApi.get).mockRejectedValue(new ApiError(404, { message: 'Not found' }))
      const { wrapper } = await mountEdit(99)

      expect(wrapper.text()).toContain('Цього запису немає у ваших цитатах.')
      expect(wrapper.find('form').exists()).toBe(false)
    })

    it('offers a retry when the quote cannot be loaded', async () => {
      vi.mocked(quotesApi.get).mockRejectedValueOnce(new ApiError(500, null)).mockResolvedValueOnce(existing)
      const { wrapper } = await mountEdit()
      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит')

      await wrapper.get('[role="alert"] button').trigger('click')
      await flushPromises()

      expect(field(wrapper, '#quote-content').element.value).toBe('Old text')
    })
  })
})
