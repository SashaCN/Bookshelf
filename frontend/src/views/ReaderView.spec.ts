import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, NetworkError } from '@/api/http'
import { libraryApi } from '@/api/library'
import { quotesApi } from '@/api/quotes'
import { readerApi } from '@/api/reader'
import { SAVE_INTERVAL_MS } from '@/composables/usePositionSaver'
import { SETTINGS_STORAGE_KEY } from '@/composables/useReaderSettings'
import { useLibraryStore } from '@/stores/library'
import { makeBook, makeQuote, makeUserBook } from '@/test/fixtures'
import { makeBookText, makeChapter, makeHighlight } from '@/test/readerFixtures'
import { createTestEnvironment } from '@/test/utils'
import type { UserBook } from '@/types/api'
import type { BookText, Chapter } from '@/types/reader'
import ReaderView from './ReaderView.vue'

vi.mock('@/api/reader', () => ({
  readerApi: { upload: vi.fn(), text: vi.fn(), removeText: vi.fn(), chapter: vi.fn(), savePosition: vi.fn() },
}))

vi.mock('@/api/library', () => ({
  libraryApi: { list: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn(), progress: vi.fn(), logs: vi.fn() },
  catalogApi: { search: vi.fn() },
}))

vi.mock('@/api/quotes', () => ({
  quotesApi: { list: vi.fn(), daily: vi.fn(), get: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

/** The chapter text, and what its offsets are: "Hello brave new world." is 0 to 22, the emoji is at 39 to 41. */
const FIRST_HTML = '<p>Hello brave new world.</p><p>Second paragraph 😀 here.</p>'

function chapters(): Chapter[] {
  return [
    makeChapter({ position: 0, title: 'Перший розділ', html: FIRST_HTML, characters: 47 }),
    makeChapter({ position: 1, title: 'Second chapter', html: '<p>Друга глава.</p>', characters: 12 }),
    makeChapter({ position: 2, title: null, html: '<p>The end.</p>', characters: 8 }),
  ]
}

function bookText(overrides: Partial<BookText> = {}): BookText {
  return makeBookText({
    id: 10,
    chapters_count: 3,
    characters_count: 67,
    chapters: [
      { position: 0, title: 'Перший розділ', characters: 47 },
      { position: 1, title: 'Second chapter', characters: 12 },
      { position: 2, title: null, characters: 8 },
    ],
    ...overrides,
  })
}

interface Options {
  query?: Record<string, string>
  text?: BookText
  book?: UserBook
  chapterList?: Chapter[]
  /** Instead of answering from `chapterList`. */
  fetchChapter?: (position: number) => Promise<Chapter>
}

const mounted: VueWrapper[] = []

async function mountReader({
  query = {},
  text = bookText(),
  book = makeUserBook({ id: 1, has_text: true }),
  chapterList = chapters(),
  fetchChapter = async (position) => structuredClone(chapterList[position]!),
}: Options = {}) {
  const { plugins, router } = createTestEnvironment()
  vi.mocked(libraryApi.list).mockResolvedValue([book])
  vi.mocked(readerApi.text).mockResolvedValue(text)
  vi.mocked(readerApi.chapter).mockImplementation((_id, position) => fetchChapter(position))
  vi.mocked(readerApi.savePosition).mockResolvedValue({ data: text, meta: { user_book: book } })
  await router.push({ name: 'read', params: { id: book.id }, query })

  const wrapper = mount(ReaderView, { props: { id: book.id }, global: { plugins }, attachTo: document.body })
  mounted.push(wrapper)
  await flushPromises()

  return { wrapper, router, library: useLibraryStore() }
}

function scroller(wrapper: VueWrapper) {
  return wrapper.get('.overflow-y-auto')
}

function content(wrapper: VueWrapper) {
  return wrapper.get('.reader-content')
}

function buttonWithText(wrapper: VueWrapper, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === text)
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

/** Selects text in the chapter the way a finger does, and lets the selection settle. */
async function selectInChapter(wrapper: VueWrapper, from: [string, number], to: [string, number]) {
  const paragraphs = [...content(wrapper).element.querySelectorAll('p')]
  const startNode = paragraphs.find((paragraph) => paragraph.textContent?.includes(from[0]))!.firstChild!
  const endNode = paragraphs.find((paragraph) => paragraph.textContent?.includes(to[0]))!.firstChild!
  const range = document.createRange()
  range.setStart(startNode, from[1])
  range.setEnd(endNode, to[1])
  const selection = window.getSelection()!
  selection.removeAllRanges()
  selection.addRange(range)

  document.dispatchEvent(new Event('selectionchange'))
  await vi.advanceTimersByTimeAsync(200)
  await flushPromises()
}

async function dropSelection() {
  window.getSelection()!.removeAllRanges()
  document.dispatchEvent(new Event('selectionchange'))
  await vi.advanceTimersByTimeAsync(700)
  await flushPromises()
}

describe('ReaderView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    localStorage.clear()
    // jsdom does not lay anything out: a chapter is 2000 pixels tall in a window of 500.
    vi.spyOn(Element.prototype, 'scrollHeight', 'get').mockReturnValue(2000)
    vi.spyOn(Element.prototype, 'clientHeight', 'get').mockReturnValue(500)
    Element.prototype.scrollIntoView = vi.fn()
    vi.mocked(quotesApi.list).mockResolvedValue({ data: [], links: { next: null } })
  })

  afterEach(() => {
    for (const wrapper of mounted.splice(0)) wrapper.unmount()
    window.getSelection()?.removeAllRanges()
    vi.restoreAllMocks()
    vi.useRealTimers()
    document.body.innerHTML = ''
    // @ts-expect-error jsdom has no scrollIntoView; the tests put one there
    delete Element.prototype.scrollIntoView
  })

  describe('opening a book', () => {
    it('shows a skeleton while the text is on its way, then the chapter', async () => {
      const { plugins, router } = createTestEnvironment()
      let finish: (text: BookText) => void = () => undefined
      vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1, has_text: true })])
      vi.mocked(readerApi.text).mockReturnValue(new Promise((resolve) => (finish = resolve)))
      vi.mocked(readerApi.chapter).mockImplementation(async (_id, position) => chapters()[position]!)
      await router.push({ name: 'read', params: { id: 1 } })

      const wrapper = mount(ReaderView, { props: { id: 1 }, global: { plugins }, attachTo: document.body })
      mounted.push(wrapper)
      await flushPromises()

      expect(wrapper.get('[role="status"]').attributes('aria-busy')).toBe('true')
      expect(wrapper.find('.reader-content').exists()).toBe(false)

      finish(bookText())
      await flushPromises()

      expect(wrapper.find('[aria-busy="true"][role="status"]').exists()).toBe(false)
      expect(content(wrapper).text()).toContain('Hello brave new world.')
    })

    it('shows the chapter, the title of the book and where the reader is in it', async () => {
      const { wrapper } = await mountReader()

      expect(content(wrapper).element.textContent).toBe('Hello brave new world.Second paragraph 😀 here.')
      expect(wrapper.get('header').text()).toContain('Atomic Habits')
      expect(wrapper.get('header').text()).toContain('Розділ 1 з 3 · Перший розділ')
      expect(readerApi.text).toHaveBeenCalledWith(1)
      expect(readerApi.chapter).toHaveBeenCalledWith(10, 0)
    })

    it('names a chapter that has no title by its number', async () => {
      const { wrapper } = await mountReader({ text: bookText({ position: { chapter: 2, chapter_progress: 0, updated_at: null } }) })

      expect(wrapper.get('header').text()).toContain('Розділ 3 з 3 · Розділ 3')
    })

    it('is full screen, with the way back to the book', async () => {
      const { wrapper } = await mountReader()

      expect(wrapper.get('.reader').classes()).toContain('fixed')
      expect(wrapper.get('header a').attributes('href')).toBe('/library/1')
      expect(wrapper.get('header a').attributes('aria-label')).toBe('До книги')
    })

    it('begins where the reader left off', async () => {
      const text = bookText({ position: { chapter: 1, chapter_progress: 0.5, updated_at: '2026-10-06T10:00:00+00:00' } })

      const { wrapper } = await mountReader({ text })

      expect(readerApi.chapter).toHaveBeenCalledWith(10, 1)
      expect(content(wrapper).text()).toBe('Друга глава.')
      expect(wrapper.get('header').text()).toContain('Розділ 2 з 3 · Second chapter')
      // Half way down a chapter of 2000 pixels in a window of 500.
      expect((scroller(wrapper).element as HTMLElement).scrollTop).toBe(750)
    })

    it('begins at the top of the first chapter for a text that has not been read', async () => {
      const { wrapper } = await mountReader()

      expect((scroller(wrapper).element as HTMLElement).scrollTop).toBe(0)
      expect(readerApi.chapter).not.toHaveBeenCalledWith(10, 1.5)
    })

    it('opens at a quote when it is asked to, and flashes it', async () => {
      const list = chapters()
      list[0]!.highlights = [makeHighlight({ id: 5, start: 6, end: 11 })]

      const { wrapper, router } = await mountReader({ query: { chapter: '0', highlight: '5' }, chapterList: list })

      const mark = wrapper.get('mark[data-quote-id="5"]')
      expect(mark.text()).toBe('brave')
      expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({ block: 'center' })
      expect(mark.classes()).toContain('reader-flash')
      // The address was only the way in: a reload goes back to where the reader is.
      expect(router.currentRoute.value.query).toEqual({})
      expect(router.currentRoute.value.name).toBe('read')
    })

    it('opens the chapter of the quote rather than the one the reader left off in', async () => {
      const list = chapters()
      list[1]!.highlights = [makeHighlight({ id: 9, start: 0, end: 5 })]
      const text = bookText({ position: { chapter: 2, chapter_progress: 0.9, updated_at: '2026-10-06T10:00:00+00:00' } })

      const { wrapper } = await mountReader({ query: { chapter: '1', highlight: '9' }, chapterList: list, text })

      expect(content(wrapper).text()).toBe('Друга глава.')
      expect(vi.mocked(readerApi.chapter).mock.calls[0]).toEqual([10, 1])
      expect(wrapper.get('mark').text()).toBe('Друга')
    })

    it('goes to the top of the chapter when the quote is not in it any more', async () => {
      const { wrapper } = await mountReader({ query: { chapter: '0', highlight: '404' } })

      expect(Element.prototype.scrollIntoView).not.toHaveBeenCalled()
      expect((scroller(wrapper).element as HTMLElement).scrollTop).toBe(0)
    })

    it('ignores an address that makes no sense', async () => {
      const text = bookText({ position: { chapter: 1, chapter_progress: 0, updated_at: '2026-10-06T10:00:00+00:00' } })

      const { wrapper } = await mountReader({ query: { chapter: 'abc', highlight: 'x' }, text })

      expect(content(wrapper).text()).toBe('Друга глава.')
    })

    it('keeps to the chapters the book has', async () => {
      const { wrapper } = await mountReader({ query: { chapter: '99' } })

      expect(readerApi.chapter).toHaveBeenCalledWith(10, 2)
      expect(content(wrapper).text()).toBe('The end.')
    })

    it('draws the quotes already saved from the chapter as highlights', async () => {
      const list = chapters()
      list[0]!.highlights = [makeHighlight({ id: 5, start: 6, end: 11 }), makeHighlight({ id: 6, start: 39, end: 41 })]

      const { wrapper } = await mountReader({ chapterList: list })

      expect(wrapper.findAll('mark').map((mark) => mark.text())).toEqual(['brave', '😀'])
      expect(content(wrapper).element.textContent).toBe('Hello brave new world.Second paragraph 😀 here.')
    })
  })

  describe('when the book cannot be opened', () => {
    it('says the book is not in the library', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([])
      vi.mocked(libraryApi.get).mockRejectedValue(new ApiError(404, null))
      await router.push({ name: 'read', params: { id: 1 } })

      const wrapper = mount(ReaderView, { props: { id: 1 }, global: { plugins }, attachTo: document.body })
      mounted.push(wrapper)
      await flushPromises()

      expect(wrapper.text()).toContain('Цієї книги немає у вашій бібліотеці.')
      expect(wrapper.get('a').attributes('href')).toBe('/library')
      expect(readerApi.text).not.toHaveBeenCalled()
    })

    it('says there is no text, and leads to the book', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1 })])
      vi.mocked(readerApi.text).mockRejectedValue(new ApiError(404, null))
      await router.push({ name: 'read', params: { id: 1 } })

      const wrapper = mount(ReaderView, { props: { id: 1 }, global: { plugins }, attachTo: document.body })
      mounted.push(wrapper)
      await flushPromises()

      expect(wrapper.text()).toContain('Для цієї книги немає завантаженого тексту')
      expect(wrapper.findAll('a').map((link) => link.attributes('href'))).toEqual(['/library/1', '/library/1'])
      expect(wrapper.text()).toContain('До книги')
    })

    it('says what went wrong and tries again when asked', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1, has_text: true })])
      vi.mocked(readerApi.text).mockRejectedValueOnce(new NetworkError())
      vi.mocked(readerApi.text).mockResolvedValue(bookText())
      vi.mocked(readerApi.chapter).mockImplementation(async (_id, position) => chapters()[position]!)
      await router.push({ name: 'read', params: { id: 1 } })

      const wrapper = mount(ReaderView, { props: { id: 1 }, global: { plugins }, attachTo: document.body })
      mounted.push(wrapper)
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Немає зв’язку')

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(content(wrapper).text()).toContain('Hello brave new world.')
    })

    it('says so when the first chapter cannot be fetched', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1, has_text: true })])
      vi.mocked(readerApi.text).mockResolvedValue(bookText())
      vi.mocked(readerApi.chapter).mockRejectedValue(new ApiError(500, null))
      await router.push({ name: 'read', params: { id: 1 } })

      const wrapper = mount(ReaderView, { props: { id: 1 }, global: { plugins }, attachTo: document.body })
      mounted.push(wrapper)
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося виконати запит. Спробуйте ще раз.')
      expect(wrapper.find('.reader-content').exists()).toBe(false)
    })

    it('does not mind when the whole library cannot be listed', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockRejectedValue(new NetworkError())
      vi.mocked(libraryApi.get).mockResolvedValue(makeUserBook({ id: 1, has_text: true }))
      vi.mocked(readerApi.text).mockResolvedValue(bookText())
      vi.mocked(readerApi.chapter).mockImplementation(async (_id, position) => chapters()[position]!)
      await router.push({ name: 'read', params: { id: 1 } })

      const wrapper = mount(ReaderView, { props: { id: 1 }, global: { plugins }, attachTo: document.body })
      mounted.push(wrapper)
      await flushPromises()

      expect(content(wrapper).text()).toContain('Hello brave new world.')
      expect(wrapper.get('header').text()).toContain('Atomic Habits')
    })
  })

  describe('going from chapter to chapter', () => {
    it('goes on to the next chapter, at its top, and says the last one was read to the end', async () => {
      const { wrapper } = await mountReader()
      ;(scroller(wrapper).element as HTMLElement).scrollTop = 600

      await buttonWithText(wrapper, 'Наступний розділ').trigger('click')
      await flushPromises()

      expect(content(wrapper).text()).toBe('Друга глава.')
      expect(wrapper.get('header').text()).toContain('Розділ 2 з 3 · Second chapter')
      expect((scroller(wrapper).element as HTMLElement).scrollTop).toBe(0)
      expect(readerApi.savePosition).toHaveBeenCalledWith(10, { chapter: 0, chapter_progress: 1 })
    })

    it('goes back to the previous chapter, and says where the reader was in the one they leave', async () => {
      const text = bookText({ position: { chapter: 1, chapter_progress: 0.5, updated_at: '2026-10-06T10:00:00+00:00' } })
      const { wrapper } = await mountReader({ text })

      await buttonWithText(wrapper, 'Попередній розділ').trigger('click')
      await flushPromises()

      expect(content(wrapper).text()).toContain('Hello brave new world.')
      expect(readerApi.savePosition).toHaveBeenCalledWith(10, { chapter: 1, chapter_progress: 0.5 })
    })

    it('has no way back from the first chapter, and no way on from the last', async () => {
      const first = await mountReader()
      expect(first.wrapper.findAll('button').map((button) => button.text())).toContain('Наступний розділ')
      expect(first.wrapper.findAll('button').map((button) => button.text())).not.toContain('Попередній розділ')

      const text = bookText({ position: { chapter: 2, chapter_progress: 0, updated_at: '2026-10-06T10:00:00+00:00' } })
      const last = await mountReader({ text })
      expect(last.wrapper.findAll('button').map((button) => button.text())).not.toContain('Наступний розділ')
      expect(last.wrapper.text()).toContain('Це кінець книги.')
    })

    it('has fetched the next chapter by the time the reader asks for it', async () => {
      const { wrapper } = await mountReader()

      expect(readerApi.chapter).toHaveBeenCalledWith(10, 1)
      const fetchedBefore = vi.mocked(readerApi.chapter).mock.calls.filter((call) => call[1] === 1).length

      await buttonWithText(wrapper, 'Наступний розділ').trigger('click')
      await flushPromises()

      expect(vi.mocked(readerApi.chapter).mock.calls.filter((call) => call[1] === 1)).toHaveLength(fetchedBefore)
      // And the one after that is on its way.
      expect(readerApi.chapter).toHaveBeenCalledWith(10, 2)
    })

    it('does not keep the chapters it has left far behind, which can be big', async () => {
      const { wrapper } = await mountReader()

      // To the last chapter and back: the first one is two chapters away from the last, so it is not kept.
      for (const label of ['Наступний розділ', 'Наступний розділ', 'Попередній розділ', 'Попередній розділ']) {
        await buttonWithText(wrapper, label).trigger('click')
        await flushPromises()
      }

      const fetched = vi.mocked(readerApi.chapter).mock.calls.map((call) => call[1])

      expect(fetched.filter((position) => position === 0)).toHaveLength(2)
      expect(fetched.filter((position) => position === 1)).toHaveLength(1)
      expect(content(wrapper).text()).toContain('Hello brave new world.')
    })

    it('says so when the next chapter cannot be fetched, keeps the one that is shown, and tries again when asked', async () => {
      // The second chapter fails twice: once when it is fetched ahead of time, and once when the reader asks for it.
      let failures = 0
      const { wrapper } = await mountReader({
        fetchChapter: async (position) => {
          if (position === 1 && failures++ < 2) throw new NetworkError()
          return chapters()[position]!
        },
      })

      await buttonWithText(wrapper, 'Наступний розділ').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Немає зв’язку')
      expect(content(wrapper).text()).toContain('Hello brave new world.')

      await buttonWithText(wrapper, 'Повторити').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(content(wrapper).text()).toBe('Друга глава.')
    })
  })

  describe('the contents', () => {
    it('lists the chapters, marks the one the reader is in and goes to the one that is chosen', async () => {
      const { wrapper } = await mountReader()

      await wrapper.get('button[aria-label="Зміст"]').trigger('click')

      const dialog = wrapper.get('[role="dialog"]')
      expect(dialog.attributes('aria-modal')).toBe('true')
      expect(dialog.findAll('li').map((item) => item.text())).toEqual([
        '1Перший розділЗараз ви тут'.replace('Зараз ви тут', 'Ви тут'),
        '2Second chapter',
        '3Розділ 3',
      ])
      expect(dialog.get('[aria-current="true"]').text()).toContain('Перший розділ')

      await dialog.findAll('li')[1]!.get('button').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(content(wrapper).text()).toBe('Друга глава.')
    })

    it('closes without going anywhere on Escape, on the button and on a tap beside it', async () => {
      const { wrapper } = await mountReader()

      await wrapper.get('button[aria-label="Зміст"]').trigger('click')
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
      await flushPromises()
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

      await wrapper.get('button[aria-label="Зміст"]').trigger('click')
      await wrapper.get('button[aria-label="Закрити зміст"]').trigger('click')
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

      await wrapper.get('button[aria-label="Зміст"]').trigger('click')
      await wrapper.get('.bg-black\\/40').trigger('click')
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

      expect(content(wrapper).text()).toContain('Hello brave new world.')
    })
  })

  describe('the look of the text', () => {
    it('starts with the defaults', async () => {
      const { wrapper } = await mountReader()
      const style = wrapper.get('.reader').attributes('style')

      expect(style).toContain('--reader-size: 18px')
      expect(style).toContain('--reader-leading: 1.6')
      expect(wrapper.get('.reader').attributes('data-theme')).toBe('auto')
    })

    it('changes the size, the line height, the typeface and the theme, and keeps them on the device', async () => {
      const { wrapper } = await mountReader()
      await wrapper.get('button[aria-label="Налаштування вигляду"]').trigger('click')
      const dialog = wrapper.get('[role="dialog"]')

      await dialog.get('button[aria-label="Більший шрифт"]').trigger('click')
      await dialog.get('button[aria-label="Більший шрифт"]').trigger('click')
      await dialog.get('button[aria-label="Більший інтервал"]').trigger('click')
      await buttonWithText(wrapper, 'Без засічок').trigger('click')
      await buttonWithText(wrapper, 'Сепія').trigger('click')

      const style = wrapper.get('.reader').attributes('style')
      expect(style).toContain('--reader-size: 20px')
      expect(style).toContain('--reader-leading: 1.7')
      expect(style).toContain('system-ui')
      expect(style).toContain('--reader-bg: #f4ecd8')
      expect(wrapper.get('.reader').attributes('data-theme')).toBe('sepia')
      expect(buttonWithText(wrapper, 'Сепія').attributes('aria-pressed')).toBe('true')
      expect(buttonWithText(wrapper, 'Світла').attributes('aria-pressed')).toBe('false')

      expect(JSON.parse(localStorage.getItem(SETTINGS_STORAGE_KEY)!)).toEqual({ fontSize: 20, lineHeight: 1.7, font: 'sans', theme: 'sepia' })
    })

    it('comes back the way the reader left it', async () => {
      localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify({ fontSize: 24, lineHeight: 2, font: 'sans', theme: 'dark' }))

      const { wrapper } = await mountReader()
      const style = wrapper.get('.reader').attributes('style')

      expect(style).toContain('--reader-size: 24px')
      expect(style).toContain('--reader-leading: 2')
      expect(style).toContain('--reader-bg: #0e1116')
      expect(wrapper.get('.reader').attributes('data-theme')).toBe('dark')
    })

    it('goes back to the defaults when asked', async () => {
      localStorage.setItem(SETTINGS_STORAGE_KEY, JSON.stringify({ fontSize: 24, lineHeight: 2, font: 'sans', theme: 'dark' }))
      const { wrapper } = await mountReader()
      await wrapper.get('button[aria-label="Налаштування вигляду"]').trigger('click')

      await buttonWithText(wrapper, 'Скинути налаштування').trigger('click')

      expect(wrapper.get('.reader').attributes('style')).toContain('--reader-size: 18px')
      expect(wrapper.get('.reader').attributes('data-theme')).toBe('auto')
    })

    it('reads nonsense in the storage as the defaults, and works without storage', async () => {
      localStorage.setItem(SETTINGS_STORAGE_KEY, '{"fontSize": "huge", "theme": "neon"')
      vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
        throw new DOMException('full', 'QuotaExceededError')
      })

      const { wrapper } = await mountReader()
      await wrapper.get('button[aria-label="Налаштування вигляду"]').trigger('click')
      await wrapper.get('button[aria-label="Більший шрифт"]').trigger('click')

      expect(wrapper.get('.reader').attributes('style')).toContain('--reader-size: 19px')
      expect(wrapper.get('.reader').attributes('data-theme')).toBe('auto')
    })
  })

  describe('how far the reader has got', () => {
    it('is a thin line for the whole book, which follows the reader down the chapter', async () => {
      const text = bookText({ position: { chapter: 1, chapter_progress: 0.5, updated_at: '2026-10-06T10:00:00+00:00' } })
      const { wrapper } = await mountReader({ text })

      const line = () => wrapper.get('[role="progressbar"]')
      expect(line().attributes('aria-label')).toBe('Прогрес у книзі')
      // 47 characters before, and half of 12, of 67.
      expect(line().attributes('aria-valuenow')).toBe('79')

      ;(scroller(wrapper).element as HTMLElement).scrollTop = 1500
      await scroller(wrapper).trigger('scroll')

      expect(line().attributes('aria-valuenow')).toBe('88')
    })

    it('starts at nothing', async () => {
      const { wrapper } = await mountReader()

      expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('0')
    })
  })

  describe('saving the position', () => {
    beforeEach(() => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval'] })
    })

    it('sends it every 15 seconds while the reader reads, and takes the page of the book along', async () => {
      const moved = makeUserBook({ id: 1, has_text: true, status: 'reading', current_page: 123, text_progress: 0.4 })
      const { wrapper, library } = await mountReader()
      vi.mocked(readerApi.savePosition).mockResolvedValue({ data: bookText(), meta: { user_book: moved } })

      ;(scroller(wrapper).element as HTMLElement).scrollTop = 600
      await scroller(wrapper).trigger('scroll')
      expect(readerApi.savePosition).not.toHaveBeenCalled()

      await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
      await flushPromises()

      expect(readerApi.savePosition).toHaveBeenCalledTimes(1)
      expect(readerApi.savePosition).toHaveBeenCalledWith(10, { chapter: 0, chapter_progress: 0.4 })
      expect(library.find(1)?.current_page).toBe(123)

      // Nothing new to say.
      await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS * 3)
      expect(readerApi.savePosition).toHaveBeenCalledTimes(1)
    })

    it('sends it when the page is hidden', async () => {
      const { wrapper } = await mountReader()
      ;(scroller(wrapper).element as HTMLElement).scrollTop = 300

      Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'hidden' })
      document.dispatchEvent(new Event('visibilitychange'))
      await flushPromises()
      Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'visible' })

      // The request has to outlive the page.
      expect(readerApi.savePosition).toHaveBeenCalledWith(10, { chapter: 0, chapter_progress: 0.2 }, { keepalive: true })
    })

    it('sends it when the reader leaves', async () => {
      const { wrapper } = await mountReader()
      ;(scroller(wrapper).element as HTMLElement).scrollTop = 900

      wrapper.unmount()
      await flushPromises()

      expect(readerApi.savePosition).toHaveBeenCalledWith(10, { chapter: 0, chapter_progress: 0.6 }, { keepalive: true })
    })

    it('saves where the reader is in one book before it opens another', async () => {
      const { wrapper } = await mountReader()
      ;(scroller(wrapper).element as HTMLElement).scrollTop = 600
      vi.mocked(libraryApi.get).mockResolvedValue(makeUserBook({ id: 2, has_text: true, book: makeBook({ id: 2, title: 'Another book' }) }))
      vi.mocked(readerApi.text).mockResolvedValue(bookText({ id: 20, user_book_id: 2 }))

      await wrapper.setProps({ id: 2 })
      await flushPromises()

      expect(readerApi.savePosition).toHaveBeenCalledWith(10, { chapter: 0, chapter_progress: 0.4 })
      expect(readerApi.text).toHaveBeenLastCalledWith(2)
      expect(wrapper.get('header').text()).toContain('Another book')
      expect(readerApi.chapter).toHaveBeenCalledWith(20, 0)
    })

    it('is not bothered by a position that could not be sent, and sends it at the next time', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(readerApi.savePosition).mockRejectedValueOnce(new NetworkError())
      ;(scroller(wrapper).element as HTMLElement).scrollTop = 600

      await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
      await flushPromises()
      expect(wrapper.find('[role="alert"]').exists()).toBe(false)

      await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS)
      await flushPromises()
      expect(readerApi.savePosition).toHaveBeenCalledTimes(2)
    })

    it('has nothing to send before there is a chapter', async () => {
      const { plugins, router } = createTestEnvironment()
      vi.mocked(libraryApi.list).mockResolvedValue([makeUserBook({ id: 1, has_text: true })])
      vi.mocked(readerApi.text).mockReturnValue(new Promise(() => undefined))
      await router.push({ name: 'read', params: { id: 1 } })

      const wrapper = mount(ReaderView, { props: { id: 1 }, global: { plugins }, attachTo: document.body })
      mounted.push(wrapper)
      await vi.advanceTimersByTimeAsync(SAVE_INTERVAL_MS * 2)
      wrapper.unmount()
      await flushPromises()

      expect(readerApi.savePosition).not.toHaveBeenCalled()
    })
  })

  describe('saving a quote from the text', () => {
    beforeEach(() => {
      vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'setInterval', 'clearInterval'] })
    })

    it('has nothing to offer until something is selected', async () => {
      const { wrapper } = await mountReader()

      expect(wrapper.find('[aria-label="Збереження цитати"]').exists()).toBe(false)
      expect(wrapper.text()).not.toContain('Зберегти цитату')
    })

    it('shows the bar when a passage is selected, saves it with its place, highlights it and clears the selection', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 77, content: 'brave', user_book_id: 1 }))

      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      expect(buttonWithText(wrapper, 'Зберегти цитату').exists()).toBe(true)

      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledWith(1, { content: 'brave', note: null, anchor: { chapter: 0, start: 6, end: 11 } })
      expect(wrapper.get('mark[data-quote-id="77"]').text()).toBe('brave')
      expect(content(wrapper).element.textContent).toBe('Hello brave new world.Second paragraph 😀 here.')
      expect(wrapper.find('[aria-label="Збереження цитати"]').exists()).toBe(false)
      expect(window.getSelection()!.rangeCount).toBe(0)
      expect(wrapper.get('[role="status"]').text()).toBe('Цитату збережено.')

      await vi.advanceTimersByTimeAsync(5000)
      expect(wrapper.find('[role="status"]').exists()).toBe(false)
    })

    it('saves it with the thought of the reader about it', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 78, content: 'new world.', note: 'Nice' }))

      await selectInChapter(wrapper, ['Hello', 12], ['Hello', 22])
      await buttonWithText(wrapper, 'Додати думку').trigger('click')
      await wrapper.get('textarea').setValue('  Nice  ')
      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledWith(1, { content: 'new world.', note: 'Nice', anchor: { chapter: 0, start: 12, end: 22 } })
    })

    it('keeps the bar while the thought is written, although writing it takes the selection away', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 79 }))

      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      await buttonWithText(wrapper, 'Додати думку').trigger('click')
      await dropSelection()

      expect(wrapper.find('textarea').exists()).toBe(true)

      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledWith(1, expect.objectContaining({ content: 'brave', anchor: { chapter: 0, start: 6, end: 11 } }))
    })

    it('takes the bar away when the selection goes and no thought is being written', async () => {
      const { wrapper } = await mountReader()

      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      await dropSelection()

      expect(wrapper.find('[aria-label="Збереження цитати"]').exists()).toBe(false)
      expect(quotesApi.create).not.toHaveBeenCalled()
    })

    it('forgets the selection when the reader cancels', async () => {
      const { wrapper } = await mountReader()

      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(wrapper.find('[aria-label="Збереження цитати"]').exists()).toBe(false)
      expect(window.getSelection()!.rangeCount).toBe(0)
    })

    it('measures a passage across paragraphs and puts a line break between them', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 80 }))

      await selectInChapter(wrapper, ['Hello', 16], ['Second', 6])
      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledWith(1, { content: 'world.\nSecond', note: null, anchor: { chapter: 0, start: 16, end: 28 } })
      expect(wrapper.findAll('mark[data-quote-id="80"]').map((mark) => mark.text())).toEqual(['world.', 'Second'])
    })

    it('counts an emoji as two characters', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 81 }))

      // "paragraph 😀 here" starts at 7 in the second paragraph, which starts at 22 in the chapter.
      await selectInChapter(wrapper, ['Second', 7], ['Second', 24])
      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledWith(1, { content: 'paragraph 😀 here', note: null, anchor: { chapter: 0, start: 29, end: 46 } })
    })

    it('does not save a passage that is too long to be a quote', async () => {
      const list = chapters()
      list[0]!.html = `<p>${'Слово '.repeat(1000)}</p>`
      list[0]!.characters = 5999
      const { wrapper } = await mountReader({ chapterList: list })

      await selectInChapter(wrapper, ['Слово', 0], ['Слово', 5900])

      expect(wrapper.get('[role="alert"]').text()).toBe('Цитата задовга: можна зберегти до 5000 символів.')
      expect(buttonWithText(wrapper, 'Зберегти цитату').attributes('disabled')).toBeDefined()

      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      expect(quotesApi.create).not.toHaveBeenCalled()
    })

    it('shows that it is saving, and does not save twice', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockReturnValue(new Promise(() => undefined))

      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await buttonWithText(wrapper, 'Зберігаємо…').trigger('click')

      expect(buttonWithText(wrapper, 'Зберігаємо…').attributes('disabled')).toBeDefined()
      expect(quotesApi.create).toHaveBeenCalledTimes(1)
    })

    it('keeps the quote, without a highlight, when the server cannot tie it to the place', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create)
        .mockRejectedValueOnce(new ApiError(422, { message: 'x', errors: { anchor: ['reader.anchor_mismatch'] } }))
        .mockResolvedValueOnce(makeQuote({ id: 82, content: 'brave' }))

      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(quotesApi.create).toHaveBeenCalledTimes(2)
      expect(quotesApi.create).toHaveBeenLastCalledWith(1, { content: 'brave', note: null })
      expect(wrapper.find('mark').exists()).toBe(false)
      expect(wrapper.get('[role="status"]').text()).toBe('Цитату збережено, але виділити її в тексті не вдалося.')
      expect(wrapper.find('[aria-label="Збереження цитати"]').exists()).toBe(false)
    })

    it('says what went wrong and keeps the passage so that the reader can try again', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockRejectedValueOnce(new NetworkError())
      vi.mocked(quotesApi.create).mockResolvedValueOnce(makeQuote({ id: 83 }))

      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="alert"]').text()).toContain('Немає зв’язку')
      expect(wrapper.find('mark').exists()).toBe(false)
      expect(buttonWithText(wrapper, 'Зберегти цитату').exists()).toBe(true)

      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="alert"]').exists()).toBe(false)
      expect(wrapper.find('mark[data-quote-id="83"]').exists()).toBe(true)
    })

    it('shows the new highlight again when the reader comes back to the chapter', async () => {
      const { wrapper } = await mountReader()
      vi.mocked(quotesApi.create).mockResolvedValue(makeQuote({ id: 84, content: 'brave' }))
      await selectInChapter(wrapper, ['Hello', 6], ['Hello', 11])
      await buttonWithText(wrapper, 'Зберегти цитату').trigger('click')
      await flushPromises()

      await buttonWithText(wrapper, 'Наступний розділ').trigger('click')
      await flushPromises()
      await buttonWithText(wrapper, 'Попередній розділ').trigger('click')
      await flushPromises()

      expect(wrapper.get('mark[data-quote-id="84"]').text()).toBe('brave')
    })
  })

  describe('tapping a highlight', () => {
    async function mountWithHighlights() {
      const list = chapters()
      list[0]!.highlights = [
        makeHighlight({ id: 5, start: 6, end: 11, content: 'brave', note: 'A note of mine' }),
        makeHighlight({ id: 6, start: 6, end: 15, content: 'brave new', note: null }),
      ]

      return mountReader({ chapterList: list })
    }

    it('shows the quote and what can be done with it', async () => {
      const list = chapters()
      list[0]!.highlights = [makeHighlight({ id: 5, start: 6, end: 11, content: 'brave', note: 'A note of mine' })]
      const { wrapper } = await mountReader({ chapterList: list })

      await wrapper.get('mark[data-quote-id="5"]').trigger('click')

      const dialog = wrapper.get('[role="dialog"]')
      expect(dialog.text()).toContain('Збережена цитата')
      expect(dialog.findAll('blockquote').map((quote) => quote.text())).toEqual(['brave'])
      expect(dialog.text()).toContain('A note of mine')
      expect(dialog.findAll('a').map((link) => [link.text(), link.attributes('href')])).toEqual([['Відкрити цитату', '/quotes/5/edit']])
    })

    it('shows only the quote that is at the place that was tapped', async () => {
      const { wrapper } = await mountWithHighlights()

      // " new" is covered by the longer highlight only.
      await wrapper.findAll('mark[data-quote-id="6"]')[1]!.trigger('click')

      expect(wrapper.get('[role="dialog"]').findAll('blockquote').map((quote) => quote.text())).toEqual(['brave new'])
    })

    it('shows every quote at a place where highlights overlap, the innermost first', async () => {
      const { wrapper } = await mountWithHighlights()

      await wrapper.get('mark[data-quote-id="5"] mark[data-quote-id="6"]').trigger('click')

      const dialog = wrapper.get('[role="dialog"]')
      expect(dialog.findAll('blockquote').map((quote) => quote.text())).toEqual(['brave new', 'brave'])
      expect(dialog.findAll('a').map((link) => link.attributes('href'))).toEqual(['/quotes/6/edit', '/quotes/5/edit'])
    })

    it('removes the quote, with its highlight, after asking', async () => {
      const { wrapper } = await mountWithHighlights()
      vi.mocked(quotesApi.remove).mockResolvedValue(null)
      await wrapper.get('mark[data-quote-id="6"]').trigger('click')

      const sheet = wrapper.get('[role="dialog"]')
      await sheet.findAll('button').find((button) => button.text() === 'Прибрати виділення')!.trigger('click')

      expect(sheet.text()).toContain('Цитата буде видалена разом із виділенням.')
      expect(quotesApi.remove).not.toHaveBeenCalled()

      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(quotesApi.remove).toHaveBeenCalledWith(6)
      expect(wrapper.find('mark[data-quote-id="6"]').exists()).toBe(false)
      expect(wrapper.find('mark[data-quote-id="5"]').exists()).toBe(true)
      expect(content(wrapper).element.textContent).toBe('Hello brave new world.Second paragraph 😀 here.')
    })

    it('closes when the last quote at the place is removed', async () => {
      const list = chapters()
      list[0]!.highlights = [makeHighlight({ id: 5, start: 6, end: 11 })]
      const { wrapper } = await mountReader({ chapterList: list })
      vi.mocked(quotesApi.remove).mockResolvedValue(null)

      await wrapper.get('mark').trigger('click')
      await buttonWithText(wrapper, 'Прибрати виділення').trigger('click')
      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(wrapper.find('mark').exists()).toBe(false)
    })

    it('does not remove anything when the reader changes their mind', async () => {
      const { wrapper } = await mountWithHighlights()
      await wrapper.get('mark[data-quote-id="5"]').trigger('click')

      await wrapper.get('[role="dialog"]').findAll('button').find((button) => button.text() === 'Прибрати виділення')!.trigger('click')
      await buttonWithText(wrapper, 'Скасувати').trigger('click')

      expect(quotesApi.remove).not.toHaveBeenCalled()
      expect(wrapper.find('mark[data-quote-id="5"]').exists()).toBe(true)
    })

    it('says what went wrong and keeps the highlight when removing fails', async () => {
      const { wrapper } = await mountWithHighlights()
      vi.mocked(quotesApi.remove).mockRejectedValue(new ApiError(500, null))
      await wrapper.get('mark[data-quote-id="5"]').trigger('click')

      await wrapper.get('[role="dialog"]').findAll('button').find((button) => button.text() === 'Прибрати виділення')!.trigger('click')
      await buttonWithText(wrapper, 'Так, видалити').trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="dialog"] [role="alert"]').text()).toBe('Не вдалося виконати запит. Спробуйте ще раз.')
      expect(wrapper.find('mark[data-quote-id="5"]').exists()).toBe(true)
    })

    it('closes on Escape', async () => {
      const { wrapper } = await mountWithHighlights()
      await wrapper.get('mark[data-quote-id="5"]').trigger('click')

      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
      await flushPromises()

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })
  })
})
