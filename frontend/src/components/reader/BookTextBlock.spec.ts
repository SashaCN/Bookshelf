import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, NetworkError } from '@/api/http'
import { readerApi } from '@/api/reader'
import { apiErrors } from '@/i18n/reader.uk'
import { useLibraryStore } from '@/stores/library'
import { useQuotesStore } from '@/stores/quotes'
import { makeQuote, makeUserBook } from '@/test/fixtures'
import { makeBookText } from '@/test/readerFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { UserBook } from '@/types/api'
import QuoteCard from '../QuoteCard.vue'
import BookTextBlock from './BookTextBlock.vue'

vi.mock('@/api/reader', () => ({
  readerApi: { upload: vi.fn(), text: vi.fn(), removeText: vi.fn(), chapter: vi.fn(), savePosition: vi.fn() },
}))

/** Every code the server may answer with (see backend/app/Enums/ReaderError.php). */
const SERVER_CODES = [
  'not_epub',
  'too_large',
  'too_many_entries',
  'too_many_chapters',
  'chapter_too_large',
  'unsafe_archive',
  'drm',
  'empty',
  'invalid',
  'no_file',
  'no_text',
  'anchor_mismatch',
  'anchor_out_of_range',
  'chapter_out_of_range',
  'text_too_large',
  'quota_exceeded',
  'busy',
]

async function mountBlock(userBook: UserBook) {
  const { plugins, router } = createTestEnvironment()
  const library = useLibraryStore()
  library.upsert(userBook)
  await router.push('/library/1')

  // The block gets its entry from the store, the way the book page passes it in.
  const wrapper = mount(
    {
      components: { BookTextBlock },
      template: '<BookTextBlock :user-book="current" />',
      computed: { current: () => library.find(userBook.id)! },
    },
    { global: { plugins } },
  )

  return { wrapper, library }
}

function chooseFile(wrapper: ReturnType<typeof mount>, file: File) {
  const input = wrapper.get('input[type="file"]')
  Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })

  return input.trigger('change')
}

function epub(name = 'book.epub'): File {
  return new File(['PK'], name, { type: 'application/epub+zip' })
}

function buttonWithText(wrapper: ReturnType<typeof mount>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('BookTextBlock without a text', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('offers to upload an EPUB, and says that DRM is not supported and that the file stays private', async () => {
    const { wrapper } = await mountBlock(makeUserBook())

    expect(wrapper.text()).toContain('Читати в застосунку')
    expect(buttonWithText(wrapper, 'Завантажити EPUB').exists()).toBe(true)
    expect(wrapper.text()).toContain('DRM')
    expect(wrapper.text()).toContain('Файл бачите лише ви')
    expect(wrapper.find('a[href="/read/1"]').exists()).toBe(false)
  })

  it('takes only EPUB files in the picker', async () => {
    const { wrapper } = await mountBlock(makeUserBook())

    const input = wrapper.get('input[type="file"]')

    expect(input.attributes('accept')).toBe('.epub,application/epub+zip')
    expect(input.attributes('aria-label')).toBe('Файл книги у форматі EPUB')
  })

  it('opens the picker when the button is pressed', async () => {
    const { wrapper } = await mountBlock(makeUserBook())
    const click = vi.spyOn(HTMLInputElement.prototype, 'click').mockImplementation(() => undefined)

    await buttonWithText(wrapper, 'Завантажити EPUB').trigger('click')

    expect(click).toHaveBeenCalledTimes(1)
    click.mockRestore()
  })

  it('does not ask the server for anything until a file is chosen', async () => {
    await mountBlock(makeUserBook())

    expect(readerApi.upload).not.toHaveBeenCalled()
    expect(readerApi.text).not.toHaveBeenCalled()
  })

  it('uploads the chosen file, shows that it is busy and then offers to read', async () => {
    const { wrapper, library } = await mountBlock(makeUserBook({ id: 1 }))
    let finish: (text: ReturnType<typeof makeBookText>) => void = () => undefined
    vi.mocked(readerApi.upload).mockReturnValue(new Promise((resolve) => (finish = resolve)))
    const file = epub()

    await chooseFile(wrapper, file)

    expect(readerApi.upload).toHaveBeenCalledWith(1, file)
    const busy = buttonWithText(wrapper, 'Завантажуємо й розбираємо книгу…')
    expect(busy.attributes('disabled')).toBeDefined()
    expect(busy.attributes('aria-busy')).toBe('true')
    expect(wrapper.get('[role="status"]').text()).toBe('Завантажуємо й розбираємо книгу…')

    finish(makeBookText({ progress: 0 }))
    await flushPromises()

    expect(library.find(1)).toMatchObject({ has_text: true, text_progress: 0 })
    expect(wrapper.get('a[href="/read/1"]').text()).toBe('Читати')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('does nothing when the picker is closed without a file', async () => {
    const { wrapper } = await mountBlock(makeUserBook())
    const input = wrapper.get('input[type="file"]')
    Object.defineProperty(input.element, 'files', { configurable: true, value: [] })

    await input.trigger('change')

    expect(readerApi.upload).not.toHaveBeenCalled()
  })

  it('turns a file that is over 15 MB away without sending it', async () => {
    const { wrapper } = await mountBlock(makeUserBook())
    const big = epub()
    Object.defineProperty(big, 'size', { value: 16 * 1024 * 1024 })

    await chooseFile(wrapper, big)

    expect(readerApi.upload).not.toHaveBeenCalled()
    expect(wrapper.get('[role="alert"]').text()).toContain('до 15 МБ')
  })

  describe('when the server refuses the file', () => {
    it.each(SERVER_CODES)('says what is wrong for reader.%s, in Ukrainian', async (code) => {
      const { wrapper, library } = await mountBlock(makeUserBook())
      vi.mocked(readerApi.upload).mockRejectedValue(new ApiError(422, { message: 'The given data was invalid.', errors: { file: [`reader.${code}`] } }))

      await chooseFile(wrapper, epub())
      await flushPromises()

      const message = wrapper.get('[role="alert"]').text()
      expect(message).toBe(apiErrors[code])
      expect(message).toMatch(/[а-яіїєґ]/i)
      expect(message).not.toContain('reader.')
      expect(library.find(1)?.has_text).toBe(false)
      // The reader can try another file.
      expect(buttonWithText(wrapper, 'Завантажити EPUB').attributes('disabled')).toBeUndefined()
    })

    it('has a message for every code of the server', () => {
      expect(Object.keys(apiErrors).sort()).toEqual([...SERVER_CODES].sort())
    })

    it('says that the server is busy, which is a 503 with the code as its message, and lets the reader try again', async () => {
      const { wrapper } = await mountBlock(makeUserBook())
      vi.mocked(readerApi.upload).mockRejectedValue(new ApiError(503, { message: 'reader.busy' }))

      await chooseFile(wrapper, epub())
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Сервер зараз обробляє іншу книгу. Спробуйте за кілька секунд.')
      expect(buttonWithText(wrapper, 'Завантажити EPUB').attributes('disabled')).toBeUndefined()
    })

    it('says that the text is too big, and that the quota is used up', async () => {
      const { wrapper } = await mountBlock(makeUserBook())

      vi.mocked(readerApi.upload).mockRejectedValueOnce(new ApiError(422, { errors: { file: ['reader.text_too_large'] } }))
      await chooseFile(wrapper, epub())
      await flushPromises()
      expect(wrapper.get('[role="alert"]').text()).toContain('8 мільйонів')

      vi.mocked(readerApi.upload).mockRejectedValueOnce(new ApiError(422, { errors: { file: ['reader.quota_exceeded'] } }))
      await chooseFile(wrapper, epub())
      await flushPromises()
      expect(wrapper.get('[role="alert"]').text()).toContain('30 книг')
      expect(wrapper.get('[role="alert"]').text()).toContain('40 мільйонів')
    })

    it('says that the file is too big when the proxy in front of the server turns it away', async () => {
      const { wrapper } = await mountBlock(makeUserBook())
      vi.mocked(readerApi.upload).mockRejectedValue(new ApiError(413, null))

      await chooseFile(wrapper, epub())
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe(apiErrors.too_large)
    })

    it('says there is no connection when the network is gone', async () => {
      const { wrapper } = await mountBlock(makeUserBook())
      vi.mocked(readerApi.upload).mockRejectedValue(new NetworkError())

      await chooseFile(wrapper, epub())
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Немає зв’язку')
    })

    it('says that something went wrong for an answer it does not know', async () => {
      const { wrapper } = await mountBlock(makeUserBook())
      vi.mocked(readerApi.upload).mockRejectedValue(new ApiError(500, null))

      await chooseFile(wrapper, epub())
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
    })

    it('forgets the message when another file is chosen', async () => {
      const { wrapper } = await mountBlock(makeUserBook())
      vi.mocked(readerApi.upload).mockRejectedValueOnce(new ApiError(422, { errors: { file: ['reader.not_epub'] } }))
      vi.mocked(readerApi.upload).mockResolvedValueOnce(makeBookText())

      await chooseFile(wrapper, epub('wrong.epub'))
      await flushPromises()
      expect(wrapper.find('[role="alert"]').exists()).toBe(true)

      await chooseFile(wrapper, epub('right.epub'))
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.find('a[href="/read/1"]').exists()).toBe(true)
    })
  })
})

describe('BookTextBlock with a text', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('offers to read, with the percentage that has been read', async () => {
    const { wrapper } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.376 }))

    expect(wrapper.get('a[href="/read/1"]').text()).toBe('Продовжити читати')
    expect(wrapper.text()).toContain('Прочитано 37%')
    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('37')
    expect(wrapper.text()).not.toContain('Завантажити EPUB')
  })

  it('says "read", not "continue", for a text that has not been started', async () => {
    const { wrapper } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0 }))

    expect(wrapper.get('a[href="/read/1"]').text()).toBe('Читати')
    expect(wrapper.text()).toContain('Прочитано 0%')
  })

  it('replaces the text with a new file', async () => {
    const { wrapper, library } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
    const click = vi.spyOn(HTMLInputElement.prototype, 'click').mockImplementation(() => undefined)
    vi.mocked(readerApi.upload).mockResolvedValue(makeBookText({ progress: 0 }))

    expect(wrapper.text()).toContain('Виділення цитат у тексті буде скинуто')
    await buttonWithText(wrapper, 'Замінити файл').trigger('click')
    expect(click).toHaveBeenCalledTimes(1)

    await chooseFile(wrapper, epub())
    await flushPromises()

    expect(readerApi.upload).toHaveBeenCalledTimes(1)
    expect(library.find(1)).toMatchObject({ has_text: true, text_progress: 0 })
    expect(wrapper.get('a[href="/read/1"]').text()).toBe('Читати')
    click.mockRestore()
  })

  it('keeps the text when the new file is refused', async () => {
    const { wrapper, library } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
    vi.mocked(readerApi.upload).mockRejectedValue(new ApiError(422, { errors: { file: ['reader.drm'] } }))

    await chooseFile(wrapper, epub())
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe(apiErrors.drm)
    expect(library.find(1)).toMatchObject({ has_text: true, text_progress: 0.5 })
    expect(wrapper.get('a[href="/read/1"]').text()).toBe('Продовжити читати')
  })

  describe('removing the text', () => {
    it('asks first, and does nothing when the reader changes their mind', async () => {
      const { wrapper } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))

      await buttonWithText(wrapper, 'Видалити текст').trigger('click')

      expect(wrapper.text()).toContain('Цитати, збережені з нього, лишаться')
      expect(readerApi.removeText).not.toHaveBeenCalled()

      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(wrapper.text()).not.toContain('Цитати, збережені з нього')
      expect(readerApi.removeText).not.toHaveBeenCalled()
      expect(buttonWithText(wrapper, 'Видалити текст').exists()).toBe(true)
    })

    it('removes it when confirmed, and offers to upload again', async () => {
      const { wrapper, library } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
      vi.mocked(readerApi.removeText).mockResolvedValue(null)

      await buttonWithText(wrapper, 'Видалити текст').trigger('click')
      await buttonWithText(wrapper, 'Видалити текст').trigger('click')
      await flushPromises()

      expect(readerApi.removeText).toHaveBeenCalledWith(1)
      expect(library.find(1)).toMatchObject({ has_text: false, text_progress: null })
      expect(buttonWithText(wrapper, 'Завантажити EPUB').exists()).toBe(true)
      expect(wrapper.find('a[href="/read/1"]').exists()).toBe(false)
    })

    it('takes a text that is gone already for removed', async () => {
      const { wrapper, library } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
      vi.mocked(readerApi.removeText).mockRejectedValue(new ApiError(404, null))

      await buttonWithText(wrapper, 'Видалити текст').trigger('click')
      await buttonWithText(wrapper, 'Видалити текст').trigger('click')
      await flushPromises()

      expect(library.find(1)?.has_text).toBe(false)
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    })

    it('says what went wrong and keeps the text when removing fails', async () => {
      const { wrapper, library } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
      vi.mocked(readerApi.removeText).mockRejectedValue(new ApiError(500, null))

      await buttonWithText(wrapper, 'Видалити текст').trigger('click')
      await buttonWithText(wrapper, 'Видалити текст').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
      expect(library.find(1)?.has_text).toBe(true)
    })
  })
})

describe('BookTextBlock and the quotes that were selected from the text', () => {
  const anchor = { book_text_id: 10, chapter: 1, start: 2, end: 9 }

  function quotesWithPlaces() {
    const quotes = useQuotesStore()
    quotes.items = [
      makeQuote({ id: 1, user_book_id: 1, anchor }),
      makeQuote({ id: 2, user_book_id: 2, anchor }),
      makeQuote({ id: 3, user_book_id: 1, anchor: null }),
    ]

    return quotes
  }

  const anchors = (quotes: ReturnType<typeof useQuotesStore>) => quotes.items.map((quote) => quote.anchor)

  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('forgets where the quotes of the book were selected when a new text replaces the old', async () => {
    const { wrapper } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
    const quotes = quotesWithPlaces()
    vi.mocked(readerApi.upload).mockResolvedValue(makeBookText({ progress: 0 }))

    await chooseFile(wrapper, epub())
    await flushPromises()

    expect(anchors(quotes)).toEqual([null, anchor, null])
  })

  it('forgets them when the text is removed', async () => {
    const { wrapper } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
    const quotes = quotesWithPlaces()
    vi.mocked(readerApi.removeText).mockResolvedValue(null)

    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await flushPromises()

    expect(anchors(quotes)).toEqual([null, anchor, null])
  })

  it('forgets them when the text turns out to be gone already', async () => {
    const { wrapper } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
    const quotes = quotesWithPlaces()
    vi.mocked(readerApi.removeText).mockRejectedValue(new ApiError(404, null))

    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await flushPromises()

    expect(anchors(quotes)).toEqual([null, anchor, null])
  })

  it('keeps them while the old text is still there: a refused file or a failed removal changes nothing', async () => {
    const { wrapper } = await mountBlock(makeUserBook({ has_text: true, text_progress: 0.5 }))
    const quotes = quotesWithPlaces()
    vi.mocked(readerApi.upload).mockRejectedValue(new ApiError(422, { errors: { file: ['reader.drm'] } }))
    vi.mocked(readerApi.removeText).mockRejectedValue(new ApiError(500, null))

    await chooseFile(wrapper, epub())
    await flushPromises()
    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await flushPromises()

    expect(anchors(quotes)).toEqual([anchor, anchor, null])
  })

  it('takes "Відкрити в тексті" away from the cards of the quotes', async () => {
    const { plugins, router } = createTestEnvironment()
    const library = useLibraryStore()
    library.upsert(makeUserBook({ id: 1, has_text: true, text_progress: 0.5 }))
    const quotes = quotesWithPlaces()
    vi.mocked(readerApi.removeText).mockResolvedValue(null)
    await router.push('/library/1')

    const wrapper = mount(
      {
        components: { BookTextBlock, QuoteCard },
        template: '<div><BookTextBlock :user-book="book" /><QuoteCard v-for="quote in quotes.items" :key="quote.id" :quote="quote" /></div>',
        computed: { book: () => library.find(1)!, quotes: () => quotes },
      },
      { global: { plugins } },
    )
    const linksToText = () => wrapper.findAll('a').filter((link) => link.text() === 'Відкрити в тексті')

    expect(linksToText()).toHaveLength(2)

    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await buttonWithText(wrapper, 'Видалити текст').trigger('click')
    await flushPromises()

    // The quote of the other book keeps its link.
    expect(linksToText()).toHaveLength(1)
    expect(linksToText()[0]!.attributes('href')).toContain('/read/2')
  })
})
