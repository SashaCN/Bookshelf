import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/api/http'
import { readerApi } from '@/api/reader'
import { makeReaderBookmark, makeReaderChapter, makeReaderDoc } from '@/test/readerFixtures'
import type { ReaderBookmark } from '@/types/api'
import { createTestEnvironment } from '@/test/utils'
import ReaderView from './ReaderView.vue'

vi.mock('@/api/reader', () => ({
  readerApi: { list: vi.fn(), get: vi.fn(), upload: vi.fn(), remove: vi.fn(), savePosition: vi.fn(), link: vi.fn(), chapter: vi.fn(), bookmarks: vi.fn(), saveBookmark: vi.fn(), removeBookmark: vi.fn() },
}))

const mounted: ReturnType<typeof mount>[] = []

async function mountView(chapter = 2, toc?: { index: number; title: string }[], marks: ReaderBookmark[] = []) {
  vi.mocked(readerApi.bookmarks).mockResolvedValue(marks)
  vi.mocked(readerApi.get).mockResolvedValue(makeReaderDoc({ id: 4, position: { chapter, fraction: 0 }, toc }))
  vi.mocked(readerApi.chapter).mockImplementation(async (_id, index) =>
    makeReaderChapter({ index, html: `<p>Розділ-${index}</p>` }),
  )
  vi.mocked(readerApi.savePosition).mockResolvedValue(makeReaderDoc())
  const { plugins } = createTestEnvironment()
  const wrapper = mount(ReaderView, { props: { id: 4 }, global: { plugins }, attachTo: document.body })
  mounted.push(wrapper)
  await flushPromises()
  return wrapper
}

function buttonWithLabel(wrapper: ReturnType<typeof mount>, label: string) {
  return wrapper.get(`button[aria-label="${label}"]`)
}

function tocButton(wrapper: ReturnType<typeof mount>) {
  return wrapper.find('button[aria-haspopup="dialog"][aria-label="Зміст"]')
}

async function openFont(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('button[aria-label="Розмір шрифту"]').trigger('click')
  await flushPromises()
}

function marksButton(wrapper: ReturnType<typeof mount>) {
  return wrapper.get('button[aria-haspopup="dialog"][aria-label="Закладки"]')
}

describe('ReaderView', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
    window.localStorage.clear()
  })

  afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount())
    vi.useRealTimers()
  })

  it('opens at the saved chapter inside a strict sandbox', async () => {
    const wrapper = await mountView(2)

    const frame = wrapper.get('iframe')
    expect(readerApi.chapter).toHaveBeenCalledWith(4, 2)
    expect(frame.attributes('sandbox')).toBe('')
    expect(frame.attributes('srcdoc')).toContain('http-equiv="Content-Security-Policy"')
    expect(frame.attributes('srcdoc')).toContain("default-src 'none'")
    expect(frame.attributes('srcdoc')).toContain('img-src data:')
    expect(frame.attributes('srcdoc')).toContain('<p>Розділ-2</p>')
    expect(wrapper.text()).toContain('Розділ 3 з 12')
  })

  it('turns pages and saves the position once, debounced', async () => {
    const wrapper = await mountView(2)

    await buttonWithLabel(wrapper, 'Наступний розділ').trigger('click')
    await flushPromises()
    await buttonWithLabel(wrapper, 'Наступний розділ').trigger('click')
    await flushPromises()

    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Розділ-4')
    expect(readerApi.savePosition).not.toHaveBeenCalled()

    vi.advanceTimersByTime(800)

    expect(readerApi.savePosition).toHaveBeenCalledTimes(1)
    expect(readerApi.savePosition).toHaveBeenCalledWith(4, { chapter: 4, fraction: 0 })

    await buttonWithLabel(wrapper, 'Попередній розділ').trigger('click')
    await flushPromises()
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Розділ-3')
  })

  it('counts opening the book as reading it: saves the opened chapter once', async () => {
    await mountView(2)
    expect(readerApi.savePosition).not.toHaveBeenCalled()

    vi.advanceTimersByTime(800)
    expect(readerApi.savePosition).toHaveBeenCalledTimes(1)
    expect(readerApi.savePosition).toHaveBeenCalledWith(4, { chapter: 2, fraction: 0 })

    vi.advanceTimersByTime(5000)
    expect(readerApi.savePosition).toHaveBeenCalledTimes(1)
  })

  it('does not save twice when a chapter change follows the open quickly', async () => {
    const wrapper = await mountView(2)
    await buttonWithLabel(wrapper, 'Наступний розділ').trigger('click')
    await flushPromises()

    vi.advanceTimersByTime(800)

    expect(readerApi.savePosition).toHaveBeenCalledTimes(1)
    expect(readerApi.savePosition).toHaveBeenCalledWith(4, { chapter: 3, fraction: 0 })
  })

  it('disables the buttons at both ends', async () => {
    const first = await mountView(0)
    expect(buttonWithLabel(first, 'Попередній розділ').attributes('disabled')).toBeDefined()

    const last = await mountView(11)
    expect(buttonWithLabel(last, 'Наступний розділ').attributes('disabled')).toBeDefined()
  })

  it('changes and remembers the font size within 14-28', async () => {
    const wrapper = await mountView(0)
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('font-size: 18px')
    await openFont(wrapper)

    await wrapper.get('[aria-label="Збільшити шрифт"]').trigger('click')
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('font-size: 20px')
    expect(window.localStorage.getItem('reader.fontSize')).toBe('20')

    for (let i = 0; i < 10; i++) await wrapper.get('[aria-label="Зменшити шрифт"]').trigger('click')
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('font-size: 14px')
  })

  it('shows the chapter title and keeps the controls compact', async () => {
    vi.mocked(readerApi.get).mockResolvedValue(makeReaderDoc({ id: 4 }))
    vi.mocked(readerApi.chapter).mockResolvedValue(makeReaderChapter({ title: 'Розділ про вітер' }))
    const { plugins } = createTestEnvironment()
    const wrapper = mount(ReaderView, { props: { id: 4 }, global: { plugins } })
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Кобзар')
    expect(wrapper.text()).toContain('Розділ про вітер')
    expect(wrapper.get('[aria-label="До списку книг"]').attributes('href')).toBe('/reader')
  })

  it('turns chapters with the arrow keys, but not while typing or with a modifier', async () => {
    const live = await mountView(2)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await flushPromises()
    expect(live.get('iframe').attributes('srcdoc')).toContain('Розділ-3')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }))
    await flushPromises()
    expect(live.get('iframe').attributes('srcdoc')).toContain('Розділ-2')

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', ctrlKey: true }))
    const input = document.createElement('input')
    document.body.append(input)
    input.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'a' }))
    await flushPromises()
    input.remove()
    expect(live.get('iframe').attributes('srcdoc')).toContain('Розділ-2')

    live.unmount()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    expect(readerApi.chapter).not.toHaveBeenCalledWith(4, 4)
  })

  it('does not go past the first or the last chapter by keyboard', async () => {
    const first = await mountView(0)
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }))
    await flushPromises()
    expect(first.text()).toContain('Розділ 1 з 12')
    first.unmount()

    const last = await mountView(11)
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await flushPromises()
    expect(last.text()).toContain('Розділ 12 з 12')
    last.unmount()
  })

  it('disables the controls while a chapter is loading and gives the focus back', async () => {
    const wrapper = await mountView(2)
    let finish: (chapter: ReturnType<typeof makeReaderChapter>) => void = () => undefined
    vi.mocked(readerApi.chapter).mockReturnValue(new Promise((resolve) => { finish = resolve }))
    const next = buttonWithLabel(wrapper, 'Наступний розділ')
    ;(next.element as HTMLButtonElement).focus()

    await next.trigger('click')

    expect(next.attributes('disabled')).toBeDefined()
    expect(buttonWithLabel(wrapper, 'Попередній розділ').attributes('disabled')).toBeDefined()
    expect(wrapper.get('iframe').attributes('aria-busy')).toBe('true')

    finish(makeReaderChapter({ index: 3, html: '<p>Розділ-3</p>' }))
    await flushPromises()

    expect(wrapper.get('iframe').attributes('aria-busy')).toBe('false')
    expect(wrapper.get('[role="status"]').text()).toBe('Розділ 4 з 12')
  })

  it('moves the focus to the other button when the last chapter is reached', async () => {
    const wrapper = await mountView(10)
    const next = buttonWithLabel(wrapper, 'Наступний розділ')
    ;(next.element as HTMLButtonElement).focus()

    await next.trigger('click')
    await flushPromises()

    expect(next.attributes('disabled')).toBeDefined()
    expect(document.activeElement).toBe(buttonWithLabel(wrapper, 'Попередній розділ').element)
  })

  it('retries the chapter that failed, not the saved one', async () => {
    const wrapper = await mountView(2)
    vi.mocked(readerApi.chapter).mockRejectedValueOnce(new ApiError(500, null))

    await buttonWithLabel(wrapper, 'Наступний розділ').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не вдалося')
    expect(wrapper.find('iframe').exists()).toBe(false)

    vi.mocked(readerApi.chapter).mockImplementation(async (_id, index) => makeReaderChapter({ index, html: `<p>Розділ-${index}</p>` }))
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(readerApi.chapter).toHaveBeenLastCalledWith(4, 3)
    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Розділ-3')
  })

  it('retries loading the book when it could not be opened at all', async () => {
    vi.mocked(readerApi.get).mockRejectedValueOnce(new ApiError(500, null))
    vi.mocked(readerApi.chapter).mockResolvedValue(makeReaderChapter())
    const { plugins } = createTestEnvironment()
    const wrapper = mount(ReaderView, { props: { id: 4 }, global: { plugins } })
    await flushPromises()
    vi.mocked(readerApi.get).mockResolvedValue(makeReaderDoc({ id: 4 }))

    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(wrapper.find('iframe').exists()).toBe(true)
  })

  it('says so when the book does not exist', async () => {
    vi.mocked(readerApi.get).mockRejectedValue(new ApiError(404, null))
    const { plugins } = createTestEnvironment()
    const wrapper = mount(ReaderView, { props: { id: 4 }, global: { plugins } })
    await flushPromises()

    expect(wrapper.text()).toContain('Такої книги немає')
    expect(wrapper.find('iframe').exists()).toBe(false)
  })

  it('saves the position when the reader leaves within the debounce window', async () => {
    const wrapper = await mountView(2)
    await buttonWithLabel(wrapper, 'Наступний розділ').trigger('click')
    await flushPromises()

    wrapper.unmount()

    expect(readerApi.savePosition).toHaveBeenCalledWith(4, { chapter: 3, fraction: 0 })
  })

  it('works when the storage is blocked', async () => {
    const spy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('blocked') })
    const setSpy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('blocked') })
    const wrapper = await mountView(0)
    await openFont(wrapper)

    await wrapper.get('[aria-label="Збільшити шрифт"]').trigger('click')

    expect(wrapper.get('iframe').attributes('srcdoc')).toContain('font-size: 20px')
    spy.mockRestore()
    setSpy.mockRestore()
  })

  it('disables the font buttons at the limits', async () => {
    window.localStorage.setItem('reader.fontSize', '28')
    const big = await mountView(0)
    await openFont(big)
    expect(big.get('[aria-label="Збільшити шрифт"]').attributes('disabled')).toBeDefined()

    window.localStorage.setItem('reader.fontSize', '14')
    const small = await mountView(0)
    await openFont(small)
    expect(small.get('[aria-label="Зменшити шрифт"]').attributes('disabled')).toBeDefined()
  })

  describe('font popover', () => {
    it('is closed at first, opens with the focus inside and closes on Escape returning the focus', async () => {
      const wrapper = await mountView(0)
      const button = wrapper.get('button[aria-label="Розмір шрифту"]')
      expect(wrapper.find('[aria-label="Збільшити шрифт"]').exists()).toBe(false)

      await openFont(wrapper)
      expect(button.attributes('aria-expanded')).toBe('true')
      expect(wrapper.get('[role="dialog"]').element.contains(document.activeElement)).toBe(true)

      await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
      await flushPromises()
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(document.activeElement).toBe(button.element)
    })

    it('keeps the focus inside when a button reaches its limit', async () => {
      window.localStorage.setItem('reader.fontSize', '26')
      const wrapper = await mountView(0)
      await openFont(wrapper)
      const larger = wrapper.get('[aria-label="Збільшити шрифт"]')
      ;(larger.element as HTMLButtonElement).focus()

      await larger.trigger('click')
      await flushPromises()

      expect(larger.attributes('disabled')).toBeDefined()
      expect(document.activeElement).toBe(wrapper.get('[aria-label="Зменшити шрифт"]').element)
    })

    it('keeps the chapter label whole in the bottom bar', async () => {
      const wrapper = await mountView(0)
      expect(wrapper.get('[role="status"]').classes()).toContain('whitespace-nowrap')
    })
  })

  describe('table of contents', () => {
    const toc = [
      { index: 0, title: 'Вступ' },
      { index: 2, title: 'Ранок' },
      { index: 5, title: 'Вечір' },
    ]

    it('hides the button when there is no toc', async () => {
      expect(tocButton(await mountView(2)).exists()).toBe(false)
      expect(tocButton(await mountView(2, [])).exists()).toBe(false)
    })

    it('opens the list, moves focus to the current entry and marks it', async () => {
      const wrapper = await mountView(3, toc)

      await tocButton(wrapper).trigger('click')
      await flushPromises()

      const items = wrapper.findAll('[role="dialog"] button')
      expect(items.map((b) => b.text())).toEqual(['Вступ', 'Ранок', 'Вечір'])
      // Chapter 3 has no title of its own, so it belongs to "Ранок".
      expect(items.map((b) => b.attributes('aria-current'))).toEqual([undefined, 'true', undefined])
      expect(document.activeElement).toBe(items[1].element)
    })

    it('jumps to the chapter and saves the position, debounced', async () => {
      const wrapper = await mountView(2, toc)

      await tocButton(wrapper).trigger('click')
      await flushPromises()
      await wrapper.findAll('[role="dialog"] button')[2].trigger('click')
      await flushPromises()

      expect(readerApi.chapter).toHaveBeenLastCalledWith(4, 5)
      expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Розділ-5')
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(readerApi.savePosition).not.toHaveBeenCalled()

      vi.advanceTimersByTime(800)
      expect(readerApi.savePosition).toHaveBeenCalledWith(4, { chapter: 5, fraction: 0 })
    })

    it('closes on Escape and returns focus to the button', async () => {
      const wrapper = await mountView(2, toc)
      const button = tocButton(wrapper)

      await button.trigger('click')
      await flushPromises()
      await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
      await flushPromises()

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(document.activeElement).toBe(button.element)
    })
  })

  describe('bookmarks', () => {
    const marks = [
      makeReaderBookmark({ id: 11, chapter: 1, chapter_title: 'Ранок', note: 'Повернутися' }),
      makeReaderBookmark({ id: 12, chapter: 5 }),
    ]

    it('loads the bookmarks once and shows the state for the current chapter', async () => {
      const wrapper = await mountView(1, undefined, marks)

      expect(readerApi.bookmarks).toHaveBeenCalledTimes(1)
      const toggle = buttonWithLabel(wrapper, 'Прибрати закладку')
      expect(toggle.attributes('aria-pressed')).toBe('true')

      await buttonWithLabel(wrapper, 'Наступний розділ').trigger('click')
      await flushPromises()

      expect(buttonWithLabel(wrapper, 'Додати закладку').attributes('aria-pressed')).toBe('false')
      expect(readerApi.bookmarks).toHaveBeenCalledTimes(1)
    })

    it('adds a bookmark at once and keeps the server\'s copy', async () => {
      const wrapper = await mountView(3)
      let resolve!: (b: ReaderBookmark) => void
      vi.mocked(readerApi.saveBookmark).mockReturnValue(new Promise((r) => (resolve = r)))

      await buttonWithLabel(wrapper, 'Додати закладку').trigger('click')
      expect(readerApi.saveBookmark).toHaveBeenCalledWith(4, 3, undefined)
      expect(buttonWithLabel(wrapper, 'Прибрати закладку').attributes('aria-pressed')).toBe('true')

      resolve(makeReaderBookmark({ id: 40, chapter: 3 }))
      await flushPromises()
      await marksButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.findAll('[role="dialog"] li')).toHaveLength(1)
      expect(wrapper.get('[role="dialog"]').text()).toContain('Розділ 4')
    })

    it('removes a bookmark at once', async () => {
      vi.mocked(readerApi.removeBookmark).mockResolvedValue(null)
      const wrapper = await mountView(1, undefined, marks)

      await buttonWithLabel(wrapper, 'Прибрати закладку').trigger('click')
      expect(buttonWithLabel(wrapper, 'Додати закладку').attributes('aria-pressed')).toBe('false')
      await flushPromises()

      expect(readerApi.removeBookmark).toHaveBeenCalledWith(4, 11)
    })

    it('rolls back when adding fails', async () => {
      vi.mocked(readerApi.saveBookmark).mockRejectedValue(new ApiError(500, null))
      const wrapper = await mountView(3)

      await buttonWithLabel(wrapper, 'Додати закладку').trigger('click')
      await flushPromises()

      expect(buttonWithLabel(wrapper, 'Додати закладку').attributes('aria-pressed')).toBe('false')
    })

    it('rolls back when removing fails', async () => {
      vi.mocked(readerApi.removeBookmark).mockRejectedValue(new ApiError(500, null))
      const wrapper = await mountView(1, undefined, marks)

      await buttonWithLabel(wrapper, 'Прибрати закладку').trigger('click')
      await flushPromises()

      expect(buttonWithLabel(wrapper, 'Прибрати закладку').attributes('aria-pressed')).toBe('true')
    })

    it('lists bookmarks with the title or the chapter number and the note, focus inside', async () => {
      const wrapper = await mountView(2, undefined, marks)

      await marksButton(wrapper).trigger('click')
      await flushPromises()

      const items = wrapper.findAll('[role="dialog"] li')
      expect(items).toHaveLength(2)
      expect(items[0].text()).toContain('Ранок')
      expect(items[0].text()).toContain('Повернутися')
      expect(items[1].text()).toContain('Розділ 6')
      expect(wrapper.get('[role="dialog"]').element.contains(document.activeElement)).toBe(true)
    })

    it('shows a hint when there are no bookmarks', async () => {
      const wrapper = await mountView(2)

      await marksButton(wrapper).trigger('click')
      await flushPromises()

      expect(wrapper.get('[role="dialog"]').text()).toContain('Закладок поки немає')
    })

    it('jumps to the bookmarked chapter and closes the panel', async () => {
      const wrapper = await mountView(2, undefined, marks)

      await marksButton(wrapper).trigger('click')
      await flushPromises()
      await wrapper.findAll('[role="dialog"] li')[1].get('button').trigger('click')
      await flushPromises()

      expect(readerApi.chapter).toHaveBeenLastCalledWith(4, 5)
      expect(wrapper.get('iframe').attributes('srcdoc')).toContain('Розділ-5')
      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    })

    it('deletes a bookmark from the panel', async () => {
      vi.mocked(readerApi.removeBookmark).mockResolvedValue(null)
      const wrapper = await mountView(2, undefined, marks)

      await marksButton(wrapper).trigger('click')
      await flushPromises()
      await wrapper.get('button[aria-label="Видалити закладку «Ранок»"]').trigger('click')
      await flushPromises()

      expect(readerApi.removeBookmark).toHaveBeenCalledWith(4, 11)
      expect(wrapper.findAll('[role="dialog"] li')).toHaveLength(1)
    })

    it('saves an edited note', async () => {
      vi.mocked(readerApi.saveBookmark).mockResolvedValue({ ...marks[0], note: 'Нове' })
      const wrapper = await mountView(2, undefined, marks)

      await marksButton(wrapper).trigger('click')
      await flushPromises()
      const item = wrapper.findAll('[role="dialog"] li')[0]
      await item.get('input').setValue('Нове')
      await item.get('form').trigger('submit')
      await flushPromises()

      expect(readerApi.saveBookmark).toHaveBeenCalledWith(4, 1, 'Нове')
      expect(wrapper.findAll('[role="dialog"] li')[0].text()).toContain('Нове')
    })

    it('closes on Escape and returns focus to the button', async () => {
      const wrapper = await mountView(2, undefined, marks)
      const button = marksButton(wrapper)

      await button.trigger('click')
      await flushPromises()
      await wrapper.get('[role="dialog"]').trigger('keydown', { key: 'Escape' })
      await flushPromises()

      expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
      expect(document.activeElement).toBe(button.element)
    })
  })
})
